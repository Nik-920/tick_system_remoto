<?php

namespace Tests\Feature\Web;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Fase 3 — UX admin/super_admin: el dashboard de super_admin ofrece un acceso
 * directo a la gestión de usuarios; el de admin no (admin no gestiona usuarios).
 */
class AdminDashboardUxTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_dashboard_shows_user_management_quick_action(): void
    {
        $superAdmin = $this->createUserWithRole('super_admin');

        $response = $this->actingAs($superAdmin)->get(route('dashboard.index'));

        $response->assertOk();
        $response->assertViewIs('dashboard.admin');
        $response->assertSeeText('Gestionar usuarios');
        $response->assertSee(route('users.index'), false);
    }

    public function test_admin_dashboard_does_not_show_user_management_quick_action(): void
    {
        $admin = $this->createUserWithRole('admin');

        $response = $this->actingAs($admin)->get(route('dashboard.index'));

        $response->assertOk();
        $response->assertViewIs('dashboard.admin');
        $response->assertDontSeeText('Gestionar usuarios');
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
