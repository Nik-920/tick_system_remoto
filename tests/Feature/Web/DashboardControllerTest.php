<?php

namespace Tests\Feature\Web;

use App\Models\Category;
use App\Models\Location;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DashboardControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login_when_opening_dashboard(): void
    {
        $response = $this->get(route('dashboard.index'));

        $response->assertRedirect(route('login'));
    }

    public function test_reporter_dashboard_shows_only_related_recent_tickets(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $otherUser = $this->createUserWithRole('reporter');
        $ownedLocation = $this->createLocation('Aula 305', 'C-305');
        $assignedLocation = $this->createLocation('Aula 306', 'C-306');
        $unrelatedLocation = $this->createLocation('Aula 307', 'C-307');
        $ownedCategory = $this->createCategory('Infraestructura', 'wrench');
        $assignedCategory = $this->createCategory('Electricidad', 'bolt');
        $unrelatedCategory = $this->createCategory('Red', 'network');

        $owned = Ticket::create([
            'title' => 'Ticket propio reporter',
            'description' => 'Descripcion A',
            'reporter_id' => $reporter->id,
            'assigned_to' => $otherUser->id,
            'location_id' => $ownedLocation->id,
            'category_id' => $ownedCategory->id,
            'state' => 'open',
            'priority' => 'high',
        ]);

        $assigned = Ticket::create([
            'title' => 'Ticket asignado reporter',
            'description' => 'Descripcion B',
            'reporter_id' => $otherUser->id,
            'assigned_to' => $reporter->id,
            'location_id' => $assignedLocation->id,
            'category_id' => $assignedCategory->id,
            'state' => 'in_progress',
            'priority' => 'medium',
        ]);

        $unrelated = Ticket::create([
            'title' => 'Ticket no relacionado',
            'description' => 'Descripcion C',
            'reporter_id' => $otherUser->id,
            'assigned_to' => null,
            'location_id' => $unrelatedLocation->id,
            'category_id' => $unrelatedCategory->id,
            'state' => 'open',
            'priority' => 'low',
        ]);

        $response = $this
            ->actingAs($reporter)
            ->get(route('dashboard.index'));

        $response->assertOk();
        $response->assertViewIs('dashboard.reporter');
        $response->assertSeeText('Centro personal de reportes');
        $response->assertSeeText('Mis alertas inmediatas');
        $response->assertDontSeeText('Centro de control operativo');
        $response->assertSeeText($owned->title);
        $response->assertSeeText($assigned->title);
        $response->assertDontSeeText($unrelated->title);
    }

    public function test_maintenance_dashboard_shows_only_assigned_tickets(): void
    {
        $maintenance = $this->createUserWithRole('maintenance');
        $reporter = $this->createUserWithRole('reporter');
        $assignedLocation = $this->createLocation('Aula 310', 'C-310');
        $assignedCategory = $this->createCategory('Mantenimiento', 'settings');
        $otherLocation = $this->createLocation('Aula 311', 'C-311');
        $otherCategory = $this->createCategory('Seguridad', 'shield');

        $assigned = Ticket::create([
            'title' => 'Ticket asignado a maintenance',
            'description' => 'Descripcion maintenance',
            'reporter_id' => $reporter->id,
            'assigned_to' => $maintenance->id,
            'location_id' => $assignedLocation->id,
            'category_id' => $assignedCategory->id,
            'state' => 'open',
            'priority' => 'critical',
        ]);

        $notAssigned = Ticket::create([
            'title' => 'Ticket de otro tecnico',
            'description' => 'No debe aparecer',
            'reporter_id' => $reporter->id,
            'assigned_to' => null,
            'location_id' => $otherLocation->id,
            'category_id' => $otherCategory->id,
            'state' => 'open',
            'priority' => 'medium',
        ]);

        $response = $this
            ->actingAs($maintenance)
            ->get(route('dashboard.index'));

        $response->assertOk();
        $response->assertViewIs('dashboard.maintenance');
        $response->assertSeeText('Consola de mantenimiento');
        $response->assertSeeText('Cola operativa priorizada');
        $response->assertDontSeeText('Centro de control operativo');
        $response->assertSeeText($assigned->title);
        $response->assertDontSeeText($notAssigned->title);
    }

    public function test_admin_dashboard_shows_global_metrics_and_qr_issues(): void
    {
        $admin = $this->createUserWithRole('admin');
        $reporter = $this->createUserWithRole('reporter');
        $location = Location::create([
            'name' => 'Laboratorio Hardware',
            'building' => 'Edificio D',
            'floor' => '1',
            'room_code' => 'D-101',
            'qr_token' => 'qr-d-101',
            'qr_generation_status' => 'failed',
            'qr_last_error' => 'Error de prueba',
            'is_active' => true,
        ]);
        $category = $this->createCategory('Hardware', 'cpu');

        Ticket::create([
            'title' => 'Cableado principal dañado',
            'description' => 'Se requiere reemplazo',
            'reporter_id' => $reporter->id,
            'location_id' => $location->id,
            'category_id' => $category->id,
            'state' => 'open',
            'priority' => 'high',
        ]);

        $response = $this
            ->actingAs($admin)
            ->get(route('dashboard.index'));

        $response->assertOk();
        $response->assertViewIs('dashboard.admin');
        $response->assertSeeText('Centro de control operativo');
        $response->assertSeeText('Salud QR por estado');
        $response->assertDontSeeText('Centro personal de reportes');
        $response->assertSeeText('Laboratorio Hardware');
        $response->assertSeeText('Top ubicaciones con carga operativa');
    }

    public function test_super_admin_uses_admin_profile_dashboard(): void
    {
        $superAdmin = $this->createUserWithRole('super_admin');

        $response = $this
            ->actingAs($superAdmin)
            ->get(route('dashboard.index'));

        $response->assertOk();
        $response->assertViewIs('dashboard.admin');
        $response->assertSeeText('Perfil operativo:');
        $response->assertSeeText('Administracion');
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
        foreach (['reporter', 'maintenance', 'admin', 'super_admin'] as $roleName) {
            Role::findOrCreate($roleName, 'web');
        }
    }

    private function createLocation(string $name, string $roomCode): Location
    {
        return Location::create([
            'name' => $name,
            'building' => 'Edificio C',
            'floor' => '3',
            'room_code' => $roomCode,
            'qr_token' => 'qr-'.strtolower(str_replace(' ', '-', $roomCode)),
            'is_active' => true,
        ]);
    }

    private function createCategory(string $name, string $icon): Category
    {
        return Category::create([
            'name' => $name,
            'icon' => $icon,
            'description' => 'Incidencias de infraestructura',
        ]);
    }
}
