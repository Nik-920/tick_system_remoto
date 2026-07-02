<?php

namespace Tests\Feature\Web;

use App\Models\Category;
use App\Models\CommunityModerationLog;
use App\Models\Location;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Regression for two distinct PostgreSQL boolean bugs in this domain.
 *
 * 1) SQLSTATE[42804] boolean mismatch — Grammar::prepareBindings() converts
 *    PHP true/false to int 1/0. PDO then sends PARAM_INT, which PostgreSQL
 *    rejects for boolean columns. Fix: setter mutators on
 *    CommunityModerationLog store '1'/'0' strings so PDO uses PARAM_STR,
 *    which PostgreSQL accepts via text→boolean coercion. Sections 1-6 below.
 *
 * 2) Silent partial write on Ticket::hide() (found 2026-07-02) — Ticket's
 *    communityVisible()/assignmentLocked() accessors store the STRING
 *    'true'/'false' on pgsql for the same PARAM_STR reason above. But
 *    Ticket::casts() ALSO used to declare these keys 'boolean'. Eloquent's
 *    HasAttributes::originalIsEquivalent() routes any key with a *primitive*
 *    cast through the low-level castAttribute(), which applies PHP's native
 *    (bool) to the raw stored value directly — bypassing the accessor. PHP's
 *    (bool) 'false' is TRUE (any non-empty, non-"0" string is truthy), so a
 *    hide() that flips true -> 'false' looked identical to the original
 *    `true` and was silently dropped from the UPDATE's column list: the
 *    request "succeeded", hidden_at/hidden_by/reason were written, but
 *    community_visible stayed true forever. SQLite (this whole suite's
 *    driver, see phpunit.xml) stores 1/0 instead of 'true'/'false', and
 *    (bool) 0 is correctly false, so this was invisible to every test here
 *    despite the file's name. Fix: removed the redundant casts() entries —
 *    the accessors already fully own get/set for these two keys. Section 7
 *    below simulates the exact pgsql-fetched state via Reflection so the
 *    test is driver-independent and actually exercises the mechanism.
 */
class TicketCommunityVisibilityPostgresBooleanRegressionTest extends TestCase
{
    use RefreshDatabase;

    // ── 1. Hide — no 500, log created with strict PHP booleans ───────────────

    public function test_hide_does_not_throw_boolean_mismatch_error(): void
    {
        $admin = $this->makeAdmin();
        $ticket = $this->makeVisibleTicket();

        $this->actingAs($admin)
            ->patch(route('tickets.community.hide', $ticket), ['reason' => 'TICKET NO APORTA VALOR'])
            ->assertRedirect(route('tickets.show', $ticket));
    }

    public function test_hide_creates_log_with_strict_php_boolean_types(): void
    {
        $admin = $this->makeAdmin();
        $ticket = $this->makeVisibleTicket();

        $this->actingAs($admin)
            ->patch(route('tickets.community.hide', $ticket), ['reason' => 'Información sensible']);

        $log = CommunityModerationLog::where('ticket_id', $ticket->id)
            ->where('action', CommunityModerationLog::ACTION_HIDDEN)
            ->firstOrFail();

        $this->assertIsBool($log->previous_visible, 'previous_visible debe ser PHP bool, no int ni string');
        $this->assertIsBool($log->new_visible, 'new_visible debe ser PHP bool, no int ni string');
    }

    public function test_hide_log_previous_visible_is_true_and_new_visible_is_false(): void
    {
        $admin = $this->makeAdmin();
        $ticket = $this->makeVisibleTicket();

        $this->actingAs($admin)
            ->patch(route('tickets.community.hide', $ticket), ['reason' => 'Motivo de prueba']);

        $log = CommunityModerationLog::where('ticket_id', $ticket->id)
            ->where('action', CommunityModerationLog::ACTION_HIDDEN)
            ->firstOrFail();

        $this->assertTrue($log->previous_visible);
        $this->assertFalse($log->new_visible);
    }

    // ── 2. Restore — no 500, log created with strict PHP booleans ────────────

    public function test_restore_does_not_throw_boolean_mismatch_error(): void
    {
        $admin = $this->makeAdmin();
        $ticket = $this->makeHiddenTicket(hiddenBy: $admin);

        $this->actingAs($admin)
            ->patch(route('tickets.community.restore', $ticket))
            ->assertRedirect(route('tickets.show', $ticket));
    }

    public function test_restore_creates_log_with_strict_php_boolean_types(): void
    {
        $admin = $this->makeAdmin();
        $ticket = $this->makeHiddenTicket(hiddenBy: $admin);

        $this->actingAs($admin)
            ->patch(route('tickets.community.restore', $ticket));

        $log = CommunityModerationLog::where('ticket_id', $ticket->id)
            ->where('action', CommunityModerationLog::ACTION_RESTORED)
            ->firstOrFail();

        $this->assertIsBool($log->previous_visible, 'previous_visible debe ser PHP bool');
        $this->assertIsBool($log->new_visible, 'new_visible debe ser PHP bool');
    }

    public function test_restore_log_previous_visible_is_false_and_new_visible_is_true(): void
    {
        $admin = $this->makeAdmin();
        $ticket = $this->makeHiddenTicket(hiddenBy: $admin);

        $this->actingAs($admin)
            ->patch(route('tickets.community.restore', $ticket));

        $log = CommunityModerationLog::where('ticket_id', $ticket->id)
            ->where('action', CommunityModerationLog::ACTION_RESTORED)
            ->firstOrFail();

        $this->assertFalse($log->previous_visible);
        $this->assertTrue($log->new_visible);
    }

    // ── 3. metadata.source y previous_reason ─────────────────────────────────

    public function test_hide_log_contains_metadata_source(): void
    {
        $admin = $this->makeAdmin();
        $ticket = $this->makeVisibleTicket();

        $this->actingAs($admin)
            ->patch(route('tickets.community.hide', $ticket), ['reason' => 'Con metadata']);

        $log = CommunityModerationLog::where('ticket_id', $ticket->id)->firstOrFail();

        $this->assertIsArray($log->metadata);
        $this->assertArrayHasKey('source', $log->metadata);
        $this->assertSame('ticket_community_visibility_controller', $log->metadata['source']);
    }

    public function test_restore_log_captures_previous_visibility_reason(): void
    {
        $admin = $this->makeAdmin();
        $ticket = $this->makeHiddenTicket(reason: 'Razón original', hiddenBy: $admin);

        $this->actingAs($admin)
            ->patch(route('tickets.community.restore', $ticket));

        $log = CommunityModerationLog::where('ticket_id', $ticket->id)
            ->where('action', CommunityModerationLog::ACTION_RESTORED)
            ->firstOrFail();

        $this->assertSame('Razón original', $log->previous_reason);
    }

    public function test_hide_log_captures_null_previous_reason_when_ticket_was_visible(): void
    {
        $admin = $this->makeAdmin();
        $ticket = $this->makeVisibleTicket();

        $this->actingAs($admin)
            ->patch(route('tickets.community.hide', $ticket), ['reason' => 'Primera vez']);

        $log = CommunityModerationLog::where('ticket_id', $ticket->id)
            ->where('action', CommunityModerationLog::ACTION_HIDDEN)
            ->firstOrFail();

        $this->assertNull($log->previous_reason);
    }

    // ── 4. Consistencia ticket + log ─────────────────────────────────────────

    public function test_hide_ticket_and_log_are_consistent(): void
    {
        $admin = $this->makeAdmin();
        $ticket = $this->makeVisibleTicket();

        $this->actingAs($admin)
            ->patch(route('tickets.community.hide', $ticket), ['reason' => 'Revisión de contenido']);

        $ticket->refresh();
        $this->assertFalse((bool) $ticket->community_visible);
        $this->assertSame($admin->id, $ticket->community_hidden_by);

        $log = CommunityModerationLog::where('ticket_id', $ticket->id)
            ->where('action', CommunityModerationLog::ACTION_HIDDEN)
            ->first();

        $this->assertNotNull($log, 'El log debe existir junto al ticket actualizado');
        $this->assertSame($admin->id, $log->performed_by);
    }

    public function test_restore_ticket_and_log_are_consistent(): void
    {
        $admin = $this->makeAdmin();
        $ticket = $this->makeHiddenTicket(hiddenBy: $admin);

        $this->actingAs($admin)
            ->patch(route('tickets.community.restore', $ticket));

        $ticket->refresh();
        $this->assertTrue((bool) $ticket->community_visible);
        $this->assertNull($ticket->community_hidden_by);

        $log = CommunityModerationLog::where('ticket_id', $ticket->id)
            ->where('action', CommunityModerationLog::ACTION_RESTORED)
            ->first();

        $this->assertNotNull($log, 'El log de restore debe existir junto al ticket actualizado');
    }

    // ── 5. Autorización ───────────────────────────────────────────────────────

    public function test_reporter_cannot_hide_ticket(): void
    {
        $reporter = $this->makeUser('reporter');
        $ticket = $this->makeVisibleTicket();

        $this->actingAs($reporter)
            ->patch(route('tickets.community.hide', $ticket), ['reason' => 'Motivo'])
            ->assertForbidden();

        $this->assertDatabaseMissing('community_moderation_logs', [
            'ticket_id' => $ticket->id,
        ]);
    }

    public function test_reporter_cannot_restore_ticket(): void
    {
        $reporter = $this->makeUser('reporter');
        $admin = $this->makeAdmin();
        $ticket = $this->makeHiddenTicket(hiddenBy: $admin);

        $this->actingAs($reporter)
            ->patch(route('tickets.community.restore', $ticket))
            ->assertForbidden();
    }

    public function test_super_admin_can_hide_and_log_is_boolean(): void
    {
        $superAdmin = $this->makeUser('super_admin');
        $ticket = $this->makeVisibleTicket();

        $this->actingAs($superAdmin)
            ->patch(route('tickets.community.hide', $ticket), ['reason' => 'Acceso super admin'])
            ->assertRedirect(route('tickets.show', $ticket));

        $log = CommunityModerationLog::where('ticket_id', $ticket->id)->firstOrFail();

        $this->assertIsBool($log->previous_visible);
        $this->assertIsBool($log->new_visible);
        $this->assertTrue($log->previous_visible);
        $this->assertFalse($log->new_visible);
    }

    // ── 6. Hide/restore cycle — dos logs, booleanos correctos en ambos ────────

    public function test_hide_then_restore_creates_two_logs_with_correct_booleans(): void
    {
        $admin = $this->makeAdmin();
        $ticket = $this->makeVisibleTicket();

        $this->actingAs($admin)
            ->patch(route('tickets.community.hide', $ticket), ['reason' => 'Cycle test']);

        $this->actingAs($admin)
            ->patch(route('tickets.community.restore', $ticket));

        $logs = CommunityModerationLog::where('ticket_id', $ticket->id)
            ->orderBy('created_at')
            ->get();

        $this->assertCount(2, $logs);

        $hideLog = $logs->firstWhere('action', CommunityModerationLog::ACTION_HIDDEN);
        $restoreLog = $logs->firstWhere('action', CommunityModerationLog::ACTION_RESTORED);

        $this->assertIsBool($hideLog->previous_visible);
        $this->assertIsBool($hideLog->new_visible);
        $this->assertIsBool($restoreLog->previous_visible);
        $this->assertIsBool($restoreLog->new_visible);

        $this->assertTrue($hideLog->previous_visible);
        $this->assertFalse($hideLog->new_visible);
        $this->assertFalse($restoreLog->previous_visible);
        $this->assertTrue($restoreLog->new_visible);
    }

    // ── 7. Silent partial-write bug — dirty-tracking on pgsql string values ───

    public function test_casts_do_not_redeclare_community_visible_or_assignment_locked(): void
    {
        // Guards against reintroducing the bug documented in the class
        // docblock: both keys have their own Attribute::make() accessors,
        // so redeclaring them in casts() routes Eloquent's dirty-check
        // through the primitive (bool) caster instead of the accessor.
        $ticket = $this->makeVisibleTicket();

        $this->assertArrayNotHasKey('community_visible', $ticket->getCasts());
        $this->assertArrayNotHasKey('assignment_locked', $ticket->getCasts());
    }

    public function test_community_visible_true_to_pgsql_false_string_is_detected_as_dirty(): void
    {
        $ticket = $this->makeVisibleTicket()->fresh();

        // Simulate exactly what the pgsql branch of communityVisible()'s
        // set-mutator stores: the string 'false', not a native bool.
        $this->setRawAttribute($ticket, 'community_visible', 'false');

        $this->assertTrue(
            $ticket->isDirty('community_visible'),
            'true -> pgsql string "false" must be detected as a real change, '.
            'not silently treated as equivalent to the original true.',
        );
        $this->assertArrayHasKey('community_visible', $ticket->getDirty());
    }

    public function test_community_visible_false_to_pgsql_true_string_is_detected_as_dirty(): void
    {
        $ticket = $this->makeHiddenTicket()->fresh();

        $this->setRawAttribute($ticket, 'community_visible', 'true');

        $this->assertTrue(
            $ticket->isDirty('community_visible'),
            'false -> pgsql string "true" must be detected as a real change (restore direction).',
        );
    }

    public function test_assignment_locked_true_to_pgsql_false_string_is_detected_as_dirty(): void
    {
        $ticket = $this->makeVisibleTicket();
        $ticket->forceFill(['assignment_locked' => true])->save();
        $ticket = $ticket->fresh();

        $this->setRawAttribute($ticket, 'assignment_locked', 'false');

        $this->assertTrue(
            $ticket->isDirty('assignment_locked'),
            'Same class of bug as community_visible above — see class docblock.',
        );
    }

    /**
     * Writes directly into the model's raw $attributes array, bypassing
     * setAttribute()/the Attribute accessor entirely, to reproduce the exact
     * in-memory state a real pgsql fetch-then-mutate would produce — without
     * needing an actual PostgreSQL connection in this SQLite-backed suite.
     */
    private function setRawAttribute(Ticket $ticket, string $key, mixed $value): void
    {
        $ref = new \ReflectionClass($ticket);
        $property = $ref->getProperty('attributes');
        $property->setAccessible(true);

        $raw = $property->getValue($ticket);
        $raw[$key] = $value;
        $property->setValue($ticket, $raw);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function makeAdmin(): User
    {
        return $this->makeUser('admin');
    }

    private function makeUser(string $role): User
    {
        $this->ensureRolesExist();
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function ensureRolesExist(): void
    {
        foreach (['reporter', 'maintenance', 'admin', 'super_admin'] as $roleName) {
            Role::findOrCreate($roleName, 'web');
        }
    }

    private function makeVisibleTicket(): Ticket
    {
        $reporter = $this->makeUser('reporter');

        return Ticket::create([
            'title' => 'Ticket visible BOOLREG '.Str::random(6),
            'description' => 'Regresión boolean PostgreSQL.',
            'reporter_id' => $reporter->id,
            'location_id' => $this->makeLocation()->id,
            'category_id' => $this->makeCategory()->id,
            'state' => Ticket::STATE_OPEN,
            'priority' => 'medium',
            'community_visible' => true,
        ]);
    }

    private function makeHiddenTicket(string $reason = 'Motivo de prueba', ?User $hiddenBy = null): Ticket
    {
        $reporter = $this->makeUser('reporter');
        $moderator = $hiddenBy ?? $this->makeAdmin();

        $ticket = Ticket::create([
            'title' => 'Ticket oculto BOOLREG '.Str::random(6),
            'description' => 'Regresión boolean PostgreSQL, estado oculto.',
            'reporter_id' => $reporter->id,
            'location_id' => $this->makeLocation()->id,
            'category_id' => $this->makeCategory()->id,
            'state' => Ticket::STATE_OPEN,
            'priority' => 'medium',
            'community_visible' => false,
        ]);

        $ticket->forceFill([
            'community_hidden_at' => now(),
            'community_hidden_by' => $moderator->id,
            'community_visibility_reason' => $reason,
        ])->save();

        return $ticket;
    }

    private function makeLocation(): Location
    {
        return Location::create([
            'name' => 'Sala BoolReg Test',
            'building' => 'Edificio Test',
            'floor' => '1',
            'room_code' => 'BR-'.Str::upper(Str::random(6)),
            'qr_token' => 'qr-'.Str::lower(Str::random(12)),
            'is_active' => true,
        ]);
    }

    private function makeCategory(): Category
    {
        return Category::create([
            'name' => 'Cat-'.Str::lower(Str::random(6)),
            'icon' => 'wrench',
            'description' => 'Categoría para tests de regresión boolean',
        ]);
    }
}
