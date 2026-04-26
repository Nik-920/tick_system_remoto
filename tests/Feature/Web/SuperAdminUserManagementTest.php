<?php

namespace Tests\Feature\Web;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SuperAdminUserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_access_user_management_index(): void
    {
        $superAdmin = $this->createUserWithRole('super_admin');

        $response = $this->actingAs($superAdmin)->get(route('users.index'));

        $response->assertOk();
        $response->assertSee('Gestion de usuarios y roles');
    }

    public function test_admin_cannot_access_user_management_routes(): void
    {
        $admin = $this->createUserWithRole('admin');

        $this->actingAs($admin)
            ->get(route('users.index'))
            ->assertForbidden();
    }

    public function test_super_admin_can_create_user_with_role(): void
    {
        config([
            'services.supabase.role_sync_enabled' => false,
        ]);

        $superAdmin = $this->createUserWithRole('super_admin');

        $response = $this
            ->actingAs($superAdmin)
            ->post(route('users.store'), [
                'name' => 'Nuevo Tecnico',
                'last_name' => 'Mantenimiento',
                'email' => 'tecnico@example.test',
                'phone' => '+51 955 000 111',
                'password' => 'secret-pass-123',
                'password_confirmation' => 'secret-pass-123',
                'role' => 'maintenance',
            ]);

        $managedUser = User::query()->where('email', 'tecnico@example.test')->firstOrFail();

        $response->assertRedirect(route('users.edit', $managedUser));
        $response->assertSessionHas('status', function (string $message): bool {
            return str_contains($message, 'Usuario creado correctamente.');
        });

        $this->assertDatabaseHas('users', [
            'email' => 'tecnico@example.test',
            'name' => 'Nuevo Tecnico',
            'last_name' => 'Mantenimiento',
            'phone' => '+51 955 000 111',
        ]);

        $this->assertTrue($managedUser->fresh()->hasRole('maintenance'));
    }

    public function test_super_admin_can_update_user_basic_profile_with_last_name_and_phone(): void
    {
        $superAdmin = $this->createUserWithRole('super_admin');
        $managedUser = $this->createUserWithRole('reporter');

        $response = $this
            ->actingAs($superAdmin)
            ->patch(route('users.update', $managedUser), [
                'name' => 'Nombre Editado',
                'last_name' => 'Apellido Editado',
                'email' => 'usuario.editado@example.test',
                'phone' => '+51 988 777 666',
            ]);

        $response->assertRedirect(route('users.edit', $managedUser));
        $response->assertSessionHas('status', 'Perfil de usuario actualizado correctamente.');

        $this->assertDatabaseHas('users', [
            'id' => $managedUser->id,
            'name' => 'Nombre Editado',
            'last_name' => 'Apellido Editado',
            'email' => 'usuario.editado@example.test',
            'phone' => '+51 988 777 666',
        ]);
    }

    public function test_super_admin_can_update_other_user_role(): void
    {
        config([
            'services.supabase.role_sync_enabled' => false,
        ]);

        $superAdmin = $this->createUserWithRole('super_admin');
        $managedUser = $this->createUserWithRole('reporter');

        $response = $this
            ->actingAs($superAdmin)
            ->patch(route('users.update-role', $managedUser), [
                'role' => 'admin',
            ]);

        $response->assertRedirect(route('users.edit', $managedUser));

        $this->assertTrue($managedUser->fresh()->hasRole('admin'));
    }

    public function test_super_admin_cannot_demote_self_from_super_admin_role(): void
    {
        config([
            'services.supabase.role_sync_enabled' => false,
        ]);

        $superAdmin = $this->createUserWithRole('super_admin');

        $response = $this
            ->actingAs($superAdmin)
            ->from(route('users.edit', $superAdmin))
            ->patch(route('users.update-role', $superAdmin), [
                'role' => 'admin',
            ]);

        $response->assertRedirect(route('users.edit', $superAdmin));
        $response->assertSessionHasErrors('role');

        $this->assertTrue($superAdmin->fresh()->hasRole('super_admin'));
    }

    public function test_super_admin_can_create_user_with_avatar(): void
    {
        config([
            'services.supabase.role_sync_enabled' => false,
            'services.supabase.storage.domain_buckets.users' => 'TablaUsers',
            'services.supabase.storage.use_local_disk_for_testing' => true,
            'services.supabase.storage.testing_disk' => 'public',
            'filesystems.domain_prefixes.users' => 'users/avatars',
        ]);
        Storage::fake('public');

        $superAdmin = $this->createUserWithRole('super_admin');

        $response = $this
            ->actingAs($superAdmin)
            ->post(route('users.store'), [
                'name' => 'Nuevo Usuario Avatar',
                'last_name' => 'Con Foto',
                'email' => 'avatar-web@example.test',
                'phone' => '+51 944 121 212',
                'password' => 'secret-pass-123',
                'password_confirmation' => 'secret-pass-123',
                'role' => 'maintenance',
                'avatar_file' => UploadedFile::fake()->image('avatar.png', 100, 100),
            ]);

        $managedUser = User::query()->where('email', 'avatar-web@example.test')->firstOrFail();

        $response->assertRedirect(route('users.edit', $managedUser));
        $this->assertNotNull($managedUser->fresh()->avatar_url);
        Storage::disk('public')->assertExists('users/avatars/'.$managedUser->id.'/avatar.png');
    }

    public function test_super_admin_can_replace_user_avatar(): void
    {
        config([
            'services.supabase.storage.domain_buckets.users' => 'TablaUsers',
            'services.supabase.storage.use_local_disk_for_testing' => true,
            'services.supabase.storage.testing_disk' => 'public',
            'filesystems.domain_prefixes.users' => 'users/avatars',
        ]);
        Storage::fake('public');

        $superAdmin = $this->createUserWithRole('super_admin');
        $managedUser = $this->createUserWithRole('reporter');

        $legacyPath = 'users/avatars/'.$managedUser->id.'/legacy.png';
        Storage::disk('public')->put($legacyPath, 'legacy');
        $managedUser->forceFill([
            'avatar_url' => '/storage/v1/object/public/TablaUsers/'.$legacyPath,
        ])->save();

        $response = $this
            ->actingAs($superAdmin)
            ->post(route('users.update-avatar', $managedUser), [
                'avatar_file' => UploadedFile::fake()->image('new-avatar.jpg', 100, 100),
            ]);

        $response->assertRedirect(route('users.edit', $managedUser));
        $response->assertSessionHas('status', 'Avatar actualizado correctamente.');

        Storage::disk('public')->assertMissing($legacyPath);
        Storage::disk('public')->assertExists('users/avatars/'.$managedUser->id.'/new-avatar.jpg');
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
