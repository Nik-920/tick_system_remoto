<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Location;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Fase 4 — Cobertura de MetricsController (antes 0%).
 *
 * Verifica que el endpoint renderiza las familias de métricas esperadas y que
 * el promedio de resolución se calcula de forma portable (sin EXTRACT(EPOCH),
 * exclusivo de PostgreSQL) corriendo sobre SQLite.
 */
class MetricsControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_metrics_render_expected_gauges_and_portable_resolution_average(): void
    {
        $admin = $this->createUserWithRole('admin');
        $reporter = $this->createUserWithRole('reporter');

        $location = Location::query()->create([
            'name' => 'Aula Métricas',
            'building' => 'Edificio M',
            'floor' => '1',
            'room_code' => 'M-101',
            'qr_token' => 'qr-m-101',
            'is_active' => true,
        ]);
        $category = Category::query()->create([
            'name' => 'Métricas',
            'icon' => 'chart',
            'description' => 'Categoría para métricas.',
        ]);

        // Ticket abierto.
        Ticket::query()->create([
            'title' => 'Abierto',
            'description' => 'Pendiente.',
            'reporter_id' => $reporter->id,
            'location_id' => $location->id,
            'category_id' => $category->id,
            'state' => 'open',
            'priority' => 'high',
        ]);

        // Ticket resuelto con tiempos exactos (4h) → promedio determinista.
        $resolved = Ticket::query()->create([
            'title' => 'Resuelto',
            'description' => 'Cerrado.',
            'reporter_id' => $reporter->id,
            'location_id' => $location->id,
            'category_id' => $category->id,
            'state' => 'resolved',
            'priority' => 'medium',
        ]);
        $resolved->forceFill([
            'created_at' => '2026-01-01 00:00:00',
            'resolved_at' => '2026-01-01 04:00:00',
        ])->save();

        $response = $this->actingAs($admin)->get(route('metrics'));

        $response->assertOk();

        // Familias de métricas presentes (prueba que todas las queries corrieron).
        $response->assertSee('tick_system_total_users '.User::count(), false);
        $response->assertSee('tick_system_tickets_by_state', false);
        $response->assertSee('state="open"', false);
        $response->assertSee('tick_system_tickets_by_category', false);
        $response->assertSee('tick_system_tickets_by_location', false);
        $response->assertSee('tick_system_users_by_role', false);
        $response->assertSee('role="admin"', false);
        $response->assertSee('tick_system_tickets_by_priority', false);

        // Cálculo portable de promedio de resolución: 4 horas, sin EXTRACT(EPOCH).
        $response->assertSee('tick_system_avg_resolution_time_hours 4', false);
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
}
