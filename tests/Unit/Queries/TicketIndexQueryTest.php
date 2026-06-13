<?php

namespace Tests\Unit\Queries;

use App\Models\Category;
use App\Models\Location;
use App\Models\Ticket;
use App\Models\TicketEmbedding;
use App\Models\User;
use App\Queries\Tickets\TicketIndexQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TicketIndexQueryTest extends TestCase
{
    use RefreshDatabase;

    // ── Role scope ──────────────────────────────────────────────────────────

    public function test_reporter_scope_returns_only_own_tickets(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $otherReporter = $this->createUserWithRole('reporter');
        $location = $this->createLocation();
        $category = $this->createCategory();

        $ownTicket = $this->createTicket($reporter, $location, $category, ['title' => 'Propio reporter']);
        $otherTicket = $this->createTicket($otherReporter, $location, $category, ['title' => 'Ajeno reporter']);

        $ids = TicketIndexQuery::build($reporter, [])->pluck('id')->all();

        $this->assertContains($ownTicket->id, $ids);
        $this->assertNotContains($otherTicket->id, $ids);
    }

    public function test_maintenance_scope_returns_assigned_and_open_unassigned(): void
    {
        $maintenanceA = $this->createUserWithRole('maintenance');
        $maintenanceB = $this->createUserWithRole('maintenance');
        $reporter = $this->createUserWithRole('reporter');
        $location = $this->createLocation();
        $category = $this->createCategory();

        // Assigned to A — visible
        $ownTicket = $this->createTicket($reporter, $location, $category, ['state' => 'open']);
        $ownTicket->forceFill(['assigned_to' => $maintenanceA->id])->save();

        // Open + unassigned — visible (claim queue)
        $available = $this->createTicket($reporter, $location, $category, ['state' => 'open']);

        // Assigned to B — NOT visible
        $otherTicket = $this->createTicket($reporter, $location, $category, ['state' => 'in_progress']);
        $otherTicket->forceFill(['assigned_to' => $maintenanceB->id])->save();

        // Resolved unassigned — NOT visible
        $resolved = $this->createTicket($reporter, $location, $category, ['state' => 'resolved']);

        $ids = TicketIndexQuery::build($maintenanceA, [])->pluck('id')->all();

        $this->assertContains($ownTicket->id, $ids);
        $this->assertContains($available->id, $ids);
        $this->assertNotContains($otherTicket->id, $ids);
        $this->assertNotContains($resolved->id, $ids);
    }

    public function test_admin_sees_all_tickets(): void
    {
        $admin = $this->createUserWithRole('admin');
        $reporter = $this->createUserWithRole('reporter');
        $maintenance = $this->createUserWithRole('maintenance');
        $location = $this->createLocation();
        $category = $this->createCategory();

        $t1 = $this->createTicket($reporter, $location, $category);
        $t2 = $this->createTicket($reporter, $location, $category, ['state' => 'in_progress']);
        $t2->forceFill(['assigned_to' => $maintenance->id])->save();
        $t3 = $this->createTicket($reporter, $location, $category, ['state' => 'resolved']);

        $ids = TicketIndexQuery::build($admin, [])->pluck('id')->all();

        $this->assertContains($t1->id, $ids);
        $this->assertContains($t2->id, $ids);
        $this->assertContains($t3->id, $ids);
    }

    public function test_super_admin_sees_all_tickets(): void
    {
        $superAdmin = $this->createUserWithRole('super_admin');
        $reporter = $this->createUserWithRole('reporter');
        $location = $this->createLocation();
        $category = $this->createCategory();

        $t1 = $this->createTicket($reporter, $location, $category);
        $t2 = $this->createTicket($reporter, $location, $category, ['state' => 'rejected']);

        $ids = TicketIndexQuery::build($superAdmin, [])->pluck('id')->all();

        $this->assertContains($t1->id, $ids);
        $this->assertContains($t2->id, $ids);
    }

    // ── Return type ─────────────────────────────────────────────────────────

    public function test_build_returns_eloquent_builder(): void
    {
        $user = $this->createUserWithRole('admin');

        $result = TicketIndexQuery::build($user, []);

        $this->assertInstanceOf(Builder::class, $result);
    }

    // ── Filters ─────────────────────────────────────────────────────────────

    public function test_state_filter_restricts_results(): void
    {
        $admin = $this->createUserWithRole('admin');
        $reporter = $this->createUserWithRole('reporter');
        $location = $this->createLocation();
        $category = $this->createCategory();

        $openTicket = $this->createTicket($reporter, $location, $category, ['state' => 'open']);
        $resolvedTicket = $this->createTicket($reporter, $location, $category, ['state' => 'resolved']);

        $ids = TicketIndexQuery::build($admin, ['state' => 'open'])->pluck('id')->all();

        $this->assertContains($openTicket->id, $ids);
        $this->assertNotContains($resolvedTicket->id, $ids);
    }

    public function test_priority_filter_restricts_results(): void
    {
        $admin = $this->createUserWithRole('admin');
        $reporter = $this->createUserWithRole('reporter');
        $location = $this->createLocation();
        $category = $this->createCategory();

        $critical = $this->createTicket($reporter, $location, $category, ['priority' => 'critical']);
        $low = $this->createTicket($reporter, $location, $category, ['priority' => 'low']);

        $ids = TicketIndexQuery::build($admin, ['priority' => 'critical'])->pluck('id')->all();

        $this->assertContains($critical->id, $ids);
        $this->assertNotContains($low->id, $ids);
    }

    public function test_location_filter_restricts_results(): void
    {
        $admin = $this->createUserWithRole('admin');
        $reporter = $this->createUserWithRole('reporter');
        $locationA = $this->createLocation();
        $locationB = $this->createLocation();
        $category = $this->createCategory();

        $ticketA = $this->createTicket($reporter, $locationA, $category);
        $ticketB = $this->createTicket($reporter, $locationB, $category);

        $ids = TicketIndexQuery::build($admin, ['location_id' => $locationA->id])->pluck('id')->all();

        $this->assertContains($ticketA->id, $ids);
        $this->assertNotContains($ticketB->id, $ids);
    }

    public function test_category_filter_restricts_results(): void
    {
        $admin = $this->createUserWithRole('admin');
        $reporter = $this->createUserWithRole('reporter');
        $location = $this->createLocation();
        $categoryA = $this->createCategory();
        $categoryB = $this->createCategory();

        $ticketA = $this->createTicket($reporter, $location, $categoryA);
        $ticketB = $this->createTicket($reporter, $location, $categoryB);

        $ids = TicketIndexQuery::build($admin, ['category_id' => $categoryA->id])->pluck('id')->all();

        $this->assertContains($ticketA->id, $ids);
        $this->assertNotContains($ticketB->id, $ids);
    }

    public function test_assignment_mine_filter(): void
    {
        $admin = $this->createUserWithRole('admin');
        $reporter = $this->createUserWithRole('reporter');
        $maintenance = $this->createUserWithRole('maintenance');
        $location = $this->createLocation();
        $category = $this->createCategory();

        $mine = $this->createTicket($reporter, $location, $category, ['state' => 'in_progress']);
        $mine->forceFill(['assigned_to' => $admin->id])->save();

        $notMine = $this->createTicket($reporter, $location, $category);
        $notMine->forceFill(['assigned_to' => $maintenance->id])->save();

        $ids = TicketIndexQuery::build($admin, ['assignment' => 'mine'])->pluck('id')->all();

        $this->assertContains($mine->id, $ids);
        $this->assertNotContains($notMine->id, $ids);
    }

    public function test_assignment_unassigned_filter(): void
    {
        $admin = $this->createUserWithRole('admin');
        $reporter = $this->createUserWithRole('reporter');
        $maintenance = $this->createUserWithRole('maintenance');
        $location = $this->createLocation();
        $category = $this->createCategory();

        $unassigned = $this->createTicket($reporter, $location, $category);
        $assigned = $this->createTicket($reporter, $location, $category, ['state' => 'in_progress']);
        $assigned->forceFill(['assigned_to' => $maintenance->id])->save();

        $ids = TicketIndexQuery::build($admin, ['assignment' => 'unassigned'])->pluck('id')->all();

        $this->assertContains($unassigned->id, $ids);
        $this->assertNotContains($assigned->id, $ids);
    }

    public function test_assignment_assigned_filter(): void
    {
        $admin = $this->createUserWithRole('admin');
        $reporter = $this->createUserWithRole('reporter');
        $maintenance = $this->createUserWithRole('maintenance');
        $location = $this->createLocation();
        $category = $this->createCategory();

        $unassigned = $this->createTicket($reporter, $location, $category);
        $assigned = $this->createTicket($reporter, $location, $category, ['state' => 'in_progress']);
        $assigned->forceFill(['assigned_to' => $maintenance->id])->save();

        $ids = TicketIndexQuery::build($admin, ['assignment' => 'assigned'])->pluck('id')->all();

        $this->assertContains($assigned->id, $ids);
        $this->assertNotContains($unassigned->id, $ids);
    }

    public function test_assignment_all_applies_no_extra_filter(): void
    {
        $admin = $this->createUserWithRole('admin');
        $reporter = $this->createUserWithRole('reporter');
        $maintenance = $this->createUserWithRole('maintenance');
        $location = $this->createLocation();
        $category = $this->createCategory();

        $unassigned = $this->createTicket($reporter, $location, $category);
        $assigned = $this->createTicket($reporter, $location, $category);
        $assigned->forceFill(['assigned_to' => $maintenance->id])->save();

        $ids = TicketIndexQuery::build($admin, ['assignment' => 'all'])->pluck('id')->all();

        $this->assertContains($unassigned->id, $ids);
        $this->assertContains($assigned->id, $ids);
    }

    public function test_search_filter_matches_title_and_description(): void
    {
        $admin = $this->createUserWithRole('admin');
        $reporter = $this->createUserWithRole('reporter');
        $location = $this->createLocation();
        $category = $this->createCategory();

        $titleMatch = $this->createTicket($reporter, $location, $category, ['title' => 'Proyector BUSQUEDA_UNICA_XYZ sin imagen']);
        $descMatch = $this->createTicket($reporter, $location, $category, ['description' => 'Descripcion con BUSQUEDA_UNICA_XYZ en el texto']);
        $noMatch = $this->createTicket($reporter, $location, $category, ['title' => 'Ticket completamente diferente']);

        $ids = TicketIndexQuery::build($admin, ['search' => 'BUSQUEDA_UNICA_XYZ'])->pluck('id')->all();

        $this->assertContains($titleMatch->id, $ids);
        $this->assertContains($descMatch->id, $ids);
        $this->assertNotContains($noMatch->id, $ids);
    }

    public function test_search_does_not_bypass_reporter_scope(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $otherReporter = $this->createUserWithRole('reporter');
        $location = $this->createLocation();
        $category = $this->createCategory();

        $secretTitle = 'SECRETO_SEARCH_BYPASS_CHECK';
        $this->createTicket($otherReporter, $location, $category, ['title' => $secretTitle]);

        $ids = TicketIndexQuery::build($reporter, ['search' => $secretTitle])->pluck('id')->all();

        $this->assertEmpty($ids);
    }

    public function test_date_range_from_filter(): void
    {
        $admin = $this->createUserWithRole('admin');
        $reporter = $this->createUserWithRole('reporter');
        $location = $this->createLocation();
        $category = $this->createCategory();

        $old = $this->createTicket($reporter, $location, $category);
        $old->forceFill(['created_at' => now()->subDays(10)])->save();

        $recent = $this->createTicket($reporter, $location, $category);
        $recent->forceFill(['created_at' => now()])->save();

        $today = now()->toDateString();
        $ids = TicketIndexQuery::build($admin, ['from' => $today])->pluck('id')->all();

        $this->assertContains($recent->id, $ids);
        $this->assertNotContains($old->id, $ids);
    }

    public function test_date_range_to_filter(): void
    {
        $admin = $this->createUserWithRole('admin');
        $reporter = $this->createUserWithRole('reporter');
        $location = $this->createLocation();
        $category = $this->createCategory();

        $old = $this->createTicket($reporter, $location, $category);
        $old->forceFill(['created_at' => now()->subDays(10)])->save();

        $future = $this->createTicket($reporter, $location, $category);
        $future->forceFill(['created_at' => now()->addDay()])->save();

        $yesterday = now()->subDay()->toDateString();
        $ids = TicketIndexQuery::build($admin, ['to' => $yesterday])->pluck('id')->all();

        $this->assertContains($old->id, $ids);
        $this->assertNotContains($future->id, $ids);
    }

    public function test_duplicates_filter_returns_only_effective_duplicates(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $location = $this->createLocation();
        $category = $this->createCategory();

        $matched = $this->createTicket($reporter, $location, $category, ['title' => 'Ticket referencia']);

        $duplicate = $this->createTicket($reporter, $location, $category, ['title' => 'Ticket duplicado IA']);
        TicketEmbedding::create([
            'ticket_id' => $duplicate->id,
            'embedding_vector' => [0.1, 0.2],
            'description_hash' => hash('sha256', $duplicate->embeddingText()),
            'is_duplicate' => true,
            'matched_ticket_id' => $matched->id,
            'similarity_score' => 0.97,
            'review_status' => null,
        ]);

        $normal = $this->createTicket($reporter, $location, $category, ['title' => 'Ticket sin duplicado']);

        $ids = TicketIndexQuery::build($reporter, ['duplicates' => '1'])->pluck('id')->all();

        $this->assertContains($duplicate->id, $ids);
        $this->assertNotContains($normal->id, $ids);
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    /** @param array<string, mixed> $overrides */
    private function createTicket(
        User $reporter,
        Location $location,
        Category $category,
        array $overrides = [],
    ): Ticket {
        return Ticket::create(array_merge([
            'title' => 'Ticket test '.Str::random(6),
            'description' => 'Descripcion de test para el ticket.',
            'reporter_id' => $reporter->id,
            'location_id' => $location->id,
            'category_id' => $category->id,
            'state' => 'open',
            'priority' => 'medium',
        ], $overrides));
    }

    private function createUserWithRole(string $role): User
    {
        $this->ensureRolesExist();
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function ensureRolesExist(): void
    {
        foreach (['reporter', 'maintenance', 'admin', 'super_admin'] as $r) {
            Role::findOrCreate($r, 'web');
        }
    }

    private function createLocation(): Location
    {
        return Location::create([
            'name' => 'Test '.Str::random(6),
            'building' => 'Edificio Test',
            'floor' => '1',
            'room_code' => Str::upper(Str::random(7)),
            'qr_token' => 'qr-'.Str::lower(Str::random(12)),
            'is_active' => true,
        ]);
    }

    private function createCategory(): Category
    {
        return Category::create([
            'name' => 'Cat '.Str::random(6),
            'icon' => 'bolt',
            'description' => 'Test category',
        ]);
    }
}
