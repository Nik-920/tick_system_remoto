<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Models\Category;
use App\Models\Location;
use App\Models\StateHistory;
use App\Models\Ticket;
use App\Models\TicketMedia;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Avatar rendering rollout — /tickets/{ticket} show view.
 *
 * Verifies that real profile photos are rendered via <x-avatar :src> for
 * reporter, assignee, assignedBy, history changedBy and evidence uploadedBy
 * when those users have avatar_url set, with initials as fallback.
 */
class TicketShowUserAvatarRenderingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['reporter', 'maintenance', 'admin', 'super_admin'] as $role) {
            Role::findOrCreate($role, 'web');
        }
    }

    // ── Reporter avatar (main-info section) ───────────────────────────────────

    public function test_reporter_with_avatar_shows_img_in_main_info(): void
    {
        $reporter = $this->userWithRole('reporter');
        $reporter->forceFill(['avatar_url' => 'https://cdn.example.test/users/rep-photo.jpg'])->save();

        $ticket = $this->openTicketFor($reporter);
        $admin = $this->userWithRole('admin');
        $admin->forceFill(['avatar_url' => null])->save();

        $html = (string) $this->actingAs($admin)
            ->get(route('tickets.show', $ticket->id))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('class="avatar-img"', $html);
        $this->assertStringContainsString('rep-photo.jpg?v=', $html);
    }

    public function test_reporter_without_avatar_shows_initials_in_main_info(): void
    {
        $reporter = $this->userWithRole('reporter');
        $reporter->forceFill(['name' => 'Raúl Pérez', 'avatar_url' => null])->save();

        $ticket = $this->openTicketFor($reporter);
        $admin = $this->userWithRole('admin');
        $admin->forceFill(['avatar_url' => null])->save();

        $html = (string) $this->actingAs($admin)
            ->get(route('tickets.show', $ticket->id))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('class="avatar-img"', $html);
        $this->assertStringContainsString('ticket-show__avatar--rose', $html);
    }

    // ── Assignee avatar (assignment section) ──────────────────────────────────

    public function test_assignee_with_avatar_shows_img_in_assignment_section(): void
    {
        $reporter = $this->userWithRole('reporter');
        $reporter->forceFill(['avatar_url' => null])->save();

        $ticket = $this->openTicketFor($reporter);

        $maintenance = $this->userWithRole('maintenance');
        $maintenance->forceFill(['avatar_url' => 'https://cdn.example.test/users/tech-photo.jpg'])->save();

        $ticket->forceFill(['assigned_to' => $maintenance->id])->save();

        $admin = $this->userWithRole('admin');
        $admin->forceFill(['avatar_url' => null])->save();

        $html = (string) $this->actingAs($admin)
            ->get(route('tickets.show', $ticket->id))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('class="avatar-img"', $html);
        $this->assertStringContainsString('tech-photo.jpg?v=', $html);
    }

    public function test_assignee_without_avatar_shows_initials_in_assignment_section(): void
    {
        $reporter = $this->userWithRole('reporter');
        $reporter->forceFill(['avatar_url' => null])->save();

        $ticket = $this->openTicketFor($reporter);

        $maintenance = $this->userWithRole('maintenance');
        $maintenance->forceFill(['avatar_url' => null])->save();
        $ticket->forceFill(['assigned_to' => $maintenance->id])->save();

        $admin = $this->userWithRole('admin');
        $admin->forceFill(['avatar_url' => null])->save();

        $html = (string) $this->actingAs($admin)
            ->get(route('tickets.show', $ticket->id))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('class="avatar-img"', $html);
        $this->assertStringContainsString('ticket-show__avatar--primary', $html);
    }

    // ── AssignedBy avatar (assignment section) ────────────────────────────────

    public function test_assigned_by_with_avatar_shows_img_in_assignment_section(): void
    {
        $reporter = $this->userWithRole('reporter');
        $reporter->forceFill(['avatar_url' => null])->save();

        $ticket = $this->openTicketFor($reporter);

        $admin = $this->userWithRole('admin');
        $admin->forceFill(['avatar_url' => 'https://cdn.example.test/users/admin-photo.jpg'])->save();

        $ticket->forceFill(['assigned_by' => $admin->id])->save();

        $html = (string) $this->actingAs($admin)
            ->get(route('tickets.show', $ticket->id))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('class="avatar-img"', $html);
        $this->assertStringContainsString('admin-photo.jpg?v=', $html);
    }

    // ── History dot avatars ───────────────────────────────────────────────────

    public function test_history_changed_by_with_avatar_shows_img_in_timeline_dot(): void
    {
        $reporter = $this->userWithRole('reporter');
        $reporter->forceFill(['avatar_url' => 'https://cdn.example.test/users/hist-actor.jpg'])->save();

        $ticket = $this->openTicketFor($reporter);
        $this->historyFor($ticket, $reporter, null, 'open');

        $admin = $this->userWithRole('admin');
        $admin->forceFill(['avatar_url' => null])->save();

        $html = (string) $this->actingAs($admin)
            ->get(route('tickets.show', $ticket->id))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('ticket-show__history-dot', $html);
        $this->assertStringContainsString('class="avatar-img"', $html);
        $this->assertStringContainsString('hist-actor.jpg?v=', $html);
    }

    public function test_history_changed_by_without_avatar_shows_initials_in_dot(): void
    {
        $reporter = $this->userWithRole('reporter');
        $reporter->forceFill(['name' => 'Luis Torres', 'avatar_url' => null])->save();

        $ticket = $this->openTicketFor($reporter);
        $this->historyFor($ticket, $reporter, null, 'open');

        $admin = $this->userWithRole('admin');
        $admin->forceFill(['avatar_url' => null])->save();

        $html = (string) $this->actingAs($admin)
            ->get(route('tickets.show', $ticket->id))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('ticket-show__history-dot', $html);
        $this->assertStringNotContainsString('class="avatar-img"', $html);
    }

    // ── Evidence uploader avatar ──────────────────────────────────────────────

    public function test_evidence_uploader_with_avatar_shows_img_in_evidence_section(): void
    {
        $reporter = $this->userWithRole('reporter');
        $reporter->forceFill(['avatar_url' => 'https://cdn.example.test/users/uploader-photo.jpg'])->save();

        $ticket = $this->openTicketFor($reporter);
        $this->mediaFor($ticket, $reporter, 'https://storage.example.test/evidence.jpg', 'image/jpeg');

        $admin = $this->userWithRole('admin');
        $admin->forceFill(['avatar_url' => null])->save();

        $html = (string) $this->actingAs($admin)
            ->get(route('tickets.show', $ticket->id))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('class="avatar-img"', $html);
        $this->assertStringContainsString('uploader-photo.jpg?v=', $html);
    }

    // ── Structural integrity ──────────────────────────────────────────────────

    public function test_ticket_show_avatar_css_classes_preserved(): void
    {
        $reporter = $this->userWithRole('reporter');
        $reporter->forceFill(['avatar_url' => null])->save();

        $ticket = $this->openTicketFor($reporter);
        $this->historyFor($ticket, $reporter, null, 'open');

        $admin = $this->userWithRole('admin');
        $admin->forceFill(['avatar_url' => null])->save();

        $html = (string) $this->actingAs($admin)
            ->get(route('tickets.show', $ticket->id))
            ->assertOk()
            ->getContent();

        // Avatar container classes must remain for tone-based coloring
        $this->assertStringContainsString('ticket-show__avatar--rose', $html);
        $this->assertStringContainsString('ticket-show__history-dot', $html);
    }

    public function test_topbar_still_renders_avatar_for_logged_in_user(): void
    {
        $admin = $this->userWithRole('admin');
        $admin->forceFill(['avatar_url' => 'https://cdn.example.test/users/admin-topbar.jpg'])->save();

        $reporter = $this->userWithRole('reporter');
        $reporter->forceFill(['avatar_url' => null])->save();
        $ticket = $this->openTicketFor($reporter);

        $html = (string) $this->actingAs($admin)
            ->get(route('tickets.show', $ticket->id))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('topbar-avatar', $html);
        $this->assertStringContainsString('class="avatar-img"', $html);
        $this->assertStringContainsString('admin-topbar.jpg?v=', $html);
    }

    public function test_avatar_url_includes_cache_busting_v_param(): void
    {
        $reporter = $this->userWithRole('reporter');
        $reporter->forceFill(['avatar_url' => 'https://cdn.example.test/users/bust-test.jpg'])->save();

        $ticket = $this->openTicketFor($reporter);
        $admin = $this->userWithRole('admin');
        $admin->forceFill(['avatar_url' => null])->save();

        $html = (string) $this->actingAs($admin)
            ->get(route('tickets.show', $ticket->id))
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression('/bust-test\.jpg\?v=\d+/', $html);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    protected function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function openTicketFor(User $reporter): Ticket
    {
        return Ticket::create([
            'title' => 'Ticket avatar test '.Str::uuid(),
            'description' => 'Descripción de prueba con largo suficiente para pasar validación.',
            'reporter_id' => $reporter->id,
            'location_id' => $this->location()->id,
            'category_id' => $this->category()->id,
            'state' => 'open',
            'priority' => 'medium',
        ]);
    }

    private function historyFor(
        Ticket $ticket,
        User $actor,
        ?string $from,
        string $to,
        ?string $comment = null,
    ): StateHistory {
        return StateHistory::create([
            'ticket_id' => $ticket->id,
            'from_state' => $from,
            'to_state' => $to,
            'changed_by' => $actor->id,
            'comment' => $comment,
        ]);
    }

    private function mediaFor(Ticket $ticket, User $uploader, string $fileUrl, string $fileType = 'image/jpeg'): TicketMedia
    {
        return TicketMedia::create([
            'ticket_id' => $ticket->id,
            'file_url' => $fileUrl,
            'file_type' => $fileType,
            'uploaded_by' => $uploader->id,
        ]);
    }

    private function location(): Location
    {
        return Location::firstOrCreate(
            ['room_code' => 'AVATST-01'],
            [
                'name' => 'Laboratorio Avatar Test',
                'building' => 'Edificio Test',
                'floor' => '1',
                'qr_token' => 'qr-avatar-test-'.Str::uuid(),
                'is_active' => true,
            ],
        );
    }

    private function category(): Category
    {
        return Category::firstOrCreate(
            ['name' => 'AvatarRenderingTest'],
            ['icon' => 'image', 'description' => 'Categoría para tests de avatar rendering'],
        );
    }
}
