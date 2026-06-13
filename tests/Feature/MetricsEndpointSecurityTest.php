<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Fase 0 — Red de seguridad para BUG-2 (P0).
 *
 * GET /metrics esta FUERA del grupo 'auth' (routes/web.php) => publico, y expone
 * total_users, users_by_role, nombres de categorias/ubicaciones y tiempos de
 * resolucion. Ademas MetricsController usa EXTRACT(EPOCH ...) (PostgreSQL-only),
 * que rompe sobre SQLite (driver de tests).
 *
 * Contrato deseado: solo admin/super_admin acceden; guests/reporters no.
 * Hoy en ROJO (acceso publico + SQL no portable). Excluible: --exclude-group phase1
 */
#[Group('phase1')]
class MetricsEndpointSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_metrics_endpoint(): void
    {
        $response = $this->get(route('metrics'));

        $response->assertRedirect(route('login'));
    }

    public function test_reporter_cannot_access_metrics_endpoint(): void
    {
        $reporter = $this->createUserWithRole('reporter');

        $response = $this->actingAs($reporter)->get(route('metrics'));

        $response->assertForbidden();
    }

    public function test_admin_can_access_metrics_endpoint(): void
    {
        $admin = $this->createUserWithRole('admin');

        $response = $this->actingAs($admin)->get(route('metrics'));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/plain; version=0.0.4; charset=UTF-8');
    }

    public function test_super_admin_can_access_metrics_endpoint(): void
    {
        $superAdmin = $this->createUserWithRole('super_admin');

        $response = $this->actingAs($superAdmin)->get(route('metrics'));

        $response->assertOk();
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
