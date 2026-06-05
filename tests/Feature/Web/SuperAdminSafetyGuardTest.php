<?php

namespace Tests\Feature\Web;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Fase 0 — Red de seguridad de invariantes de roles (superficie Web).
 *
 * Bloquea regresiones en escalamiento de privilegios y auto-destruccion:
 *  - super_admin NO puede eliminarse a si mismo (UserPolicy::delete).
 *  - el sistema nunca queda sin super_admin por self-demote/self-delete.
 *  - admin NO puede crear ni promover a super_admin (rutas role:super_admin).
 *
 * Estos tests afirman comportamiento YA correcto: deben estar en VERDE.
 * Su valor es congelar el contrato antes de tocar los controllers en Fase 1+.
 */
class SuperAdminSafetyGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_cannot_delete_self_via_web(): void
    {
        // Dos super_admins: el bloqueo debe deberse al "self", no a "ultimo super_admin".
        $superAdmin = $this->createUserWithRole('super_admin');
        $this->createUserWithRole('super_admin');

        $response = $this->actingAs($superAdmin)
            ->from(route('users.index'))
            ->delete(route('users.destroy', $superAdmin));

        $response->assertForbidden();
        $this->assertDatabaseHas('users', ['id' => $superAdmin->id]);
    }

    public function test_system_keeps_a_super_admin_after_self_demotion_attempt(): void
    {
        config(['services.supabase.role_sync_enabled' => false]);

        $superAdmin = $this->createUserWithRole('super_admin');

        $response = $this->actingAs($superAdmin)
            ->from(route('users.edit', $superAdmin))
            ->patch(route('users.update-role', $superAdmin), ['role' => 'admin']);

        $response->assertSessionHasErrors('role');
        $this->assertTrue($superAdmin->fresh()->hasRole('super_admin'));
        $this->assertGreaterThanOrEqual(1, $this->countSuperAdmins());
    }

    public function test_super_admin_can_delete_another_super_admin_when_multiple_exist_via_web(): void
    {
        // Documenta que el guard NO sobre-restringe: borrar a otro super_admin
        // estando >1 en el sistema esta permitido.
        $superAdmin = $this->createUserWithRole('super_admin');
        $otherSuperAdmin = $this->createUserWithRole('super_admin');

        $response = $this->actingAs($superAdmin)
            ->delete(route('users.destroy', $otherSuperAdmin));

        $response->assertRedirect(route('users.index'));
        $this->assertDatabaseMissing('users', ['id' => $otherSuperAdmin->id]);
    }

    public function test_admin_cannot_create_super_admin_via_web(): void
    {
        $admin = $this->createUserWithRole('admin');

        $response = $this->actingAs($admin)
            ->post(route('users.store'), [
                'name' => 'Intento',
                'last_name' => 'Escalada',
                'email' => 'escalada-web@example.test',
                'phone' => '+51 900 000 000',
                'password' => 'secret-pass-123',
                'password_confirmation' => 'secret-pass-123',
                'role' => 'super_admin',
            ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('users', ['email' => 'escalada-web@example.test']);
    }

    public function test_admin_cannot_promote_user_to_super_admin_via_web(): void
    {
        $admin = $this->createUserWithRole('admin');
        $target = $this->createUserWithRole('reporter');

        $response = $this->actingAs($admin)
            ->patch(route('users.update-role', $target), ['role' => 'super_admin']);

        $response->assertForbidden();
        $this->assertFalse($target->fresh()->hasRole('super_admin'));
    }

    private function countSuperAdmins(): int
    {
        return Role::findOrCreate('super_admin', 'web')->users()->count();
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
