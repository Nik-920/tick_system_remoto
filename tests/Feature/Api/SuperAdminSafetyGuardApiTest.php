<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Fase 0 — Red de seguridad de invariantes de roles (superficie API) + paridad.
 *
 *  - super_admin NO puede eliminarse a si mismo via API.
 *  - admin NO puede crear super_admin via API.
 *  - PARIDAD: admin esta bloqueado de user-management en API igual que en Web.
 *
 * Comportamiento YA correcto: deben estar en VERDE. Junto a
 * SuperAdminSafetyGuardTest (Web) forman la matriz de paridad de user-management.
 */
class SuperAdminSafetyGuardApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_cannot_delete_self_via_api(): void
    {
        $superAdmin = $this->createUserWithRole('super_admin');
        $this->createUserWithRole('super_admin');
        Sanctum::actingAs($superAdmin);

        $response = $this->deleteJson(route('api.users.destroy', $superAdmin));

        $response->assertForbidden();
        $this->assertDatabaseHas('users', ['id' => $superAdmin->id]);
    }

    public function test_admin_cannot_create_super_admin_via_api(): void
    {
        $admin = $this->createUserWithRole('admin');
        Sanctum::actingAs($admin);

        $response = $this->postJson(route('api.users.store'), [
            'name' => 'Intento',
            'last_name' => 'Escalada',
            'email' => 'escalada-api@example.test',
            'phone' => '+51 900 000 000',
            'password' => 'secret-pass-123',
            'password_confirmation' => 'secret-pass-123',
            'role' => 'super_admin',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('users', ['email' => 'escalada-api@example.test']);
    }

    public function test_admin_is_blocked_from_all_user_management_endpoints_via_api(): void
    {
        $admin = $this->createUserWithRole('admin');
        $target = $this->createUserWithRole('reporter');
        Sanctum::actingAs($admin);

        $this->getJson(route('api.users.index'))->assertForbidden();
        $this->getJson(route('api.users.show', $target))->assertForbidden();
        $this->patchJson(route('api.users.update', $target), [
            'name' => 'X',
            'last_name' => 'Y',
            'email' => 'x@example.test',
        ])->assertForbidden();
        $this->patchJson(route('api.users.update-role', $target), ['role' => 'admin'])
            ->assertForbidden();
        $this->deleteJson(route('api.users.destroy', $target))->assertForbidden();

        // El rol del target no debe haber cambiado pese a los intentos.
        $this->assertTrue($target->fresh()->hasRole('reporter'));
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
