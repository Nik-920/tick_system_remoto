<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserManagementApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_list_users_via_api(): void
    {
        $superAdmin = $this->createUserWithRole('super_admin');
        Sanctum::actingAs($superAdmin);

        $this->createUserWithRole('reporter');
        $this->createUserWithRole('maintenance');

        $response = $this->getJson(route('api.users.index'));

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                [
                    'id',
                    'name',
                    'last_name',
                    'email',
                    'phone',
                    'roles',
                    'primary_role',
                    'created_at',
                    'updated_at',
                ],
            ],
        ]);
    }

    public function test_admin_cannot_list_users_via_api(): void
    {
        $admin = $this->createUserWithRole('admin');
        Sanctum::actingAs($admin);

        $response = $this->getJson(route('api.users.index'));

        $response->assertForbidden();
    }

    public function test_super_admin_can_create_user_with_role_via_api(): void
    {
        config([
            'services.supabase.role_sync_enabled' => false,
        ]);

        $superAdmin = $this->createUserWithRole('super_admin');
        Sanctum::actingAs($superAdmin);

        $response = $this->postJson(route('api.users.store'), [
            'name' => 'Usuario API',
            'last_name' => 'Integracion',
            'email' => 'usuario-api@example.test',
            'phone' => '+51 933 222 111',
            'password' => 'secret-pass-123',
            'password_confirmation' => 'secret-pass-123',
            'role' => 'maintenance',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.email', 'usuario-api@example.test');
        $response->assertJsonPath('data.last_name', 'Integracion');
        $response->assertJsonPath('data.phone', '+51 933 222 111');
        $response->assertJsonPath('data.primary_role', 'maintenance');

        $this->assertDatabaseHas('users', [
            'email' => 'usuario-api@example.test',
            'last_name' => 'Integracion',
            'phone' => '+51 933 222 111',
        ]);
    }

    public function test_super_admin_can_update_user_basic_profile_with_last_name_and_phone_via_api(): void
    {
        $superAdmin = $this->createUserWithRole('super_admin');
        Sanctum::actingAs($superAdmin);

        $managedUser = $this->createUserWithRole('reporter');

        $response = $this->patchJson(route('api.users.update', $managedUser), [
            'name' => 'Nombre API',
            'last_name' => 'Apellido API',
            'email' => 'api.update@example.test',
            'phone' => '+51 944 888 777',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.last_name', 'Apellido API');
        $response->assertJsonPath('data.phone', '+51 944 888 777');

        $this->assertDatabaseHas('users', [
            'id' => $managedUser->id,
            'name' => 'Nombre API',
            'last_name' => 'Apellido API',
            'email' => 'api.update@example.test',
            'phone' => '+51 944 888 777',
        ]);
    }

    public function test_super_admin_can_update_role_via_api(): void
    {
        config([
            'services.supabase.role_sync_enabled' => false,
        ]);

        $superAdmin = $this->createUserWithRole('super_admin');
        Sanctum::actingAs($superAdmin);

        $managedUser = $this->createUserWithRole('reporter');

        $response = $this->patchJson(route('api.users.update-role', $managedUser), [
            'role' => 'admin',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.primary_role', 'admin');

        $this->assertTrue($managedUser->fresh()->hasRole('admin'));
    }

    public function test_super_admin_cannot_demote_self_via_api(): void
    {
        config([
            'services.supabase.role_sync_enabled' => false,
        ]);

        $superAdmin = $this->createUserWithRole('super_admin');
        Sanctum::actingAs($superAdmin);

        $response = $this->patchJson(route('api.users.update-role', $superAdmin), [
            'role' => 'admin',
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('message', 'No puedes quitarte a ti mismo el rol super_admin.');

        $this->assertTrue($superAdmin->fresh()->hasRole('super_admin'));
    }

    public function test_super_admin_can_create_user_with_avatar_via_api(): void
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
        Sanctum::actingAs($superAdmin);

        $response = $this
            ->withHeader('Accept', 'application/json')
            ->post(route('api.users.store'), [
                'name' => 'Usuario Avatar API',
                'last_name' => 'Foto API',
                'email' => 'avatar-api@example.test',
                'phone' => '+51 977 100 200',
                'password' => 'secret-pass-123',
                'password_confirmation' => 'secret-pass-123',
                'role' => 'maintenance',
                'avatar_file' => UploadedFile::fake()->image('avatar.png', 100, 100),
            ]);

        $response->assertCreated();
        $response->assertJsonPath('data.email', 'avatar-api@example.test');
        $this->assertNotNull($response->json('data.avatar_url'));

        $createdUser = User::query()->where('email', 'avatar-api@example.test')->firstOrFail();
        $this->assertNotNull($createdUser->avatar_url);
        Storage::disk('public')->assertExists('users/avatars/' . $createdUser->id . '/avatar.png');
    }

    public function test_super_admin_can_replace_user_avatar_via_api(): void
    {
        config([
            'services.supabase.storage.domain_buckets.users' => 'TablaUsers',
            'services.supabase.storage.use_local_disk_for_testing' => true,
            'services.supabase.storage.testing_disk' => 'public',
            'filesystems.domain_prefixes.users' => 'users/avatars',
        ]);
        Storage::fake('public');

        $superAdmin = $this->createUserWithRole('super_admin');
        Sanctum::actingAs($superAdmin);

        $managedUser = $this->createUserWithRole('reporter');

        $legacyPath = 'users/avatars/' . $managedUser->id . '/legacy.png';
        Storage::disk('public')->put($legacyPath, 'legacy');
        $managedUser->forceFill([
            'avatar_url' => '/storage/v1/object/public/TablaUsers/' . $legacyPath,
        ])->save();

        $response = $this
            ->withHeader('Accept', 'application/json')
            ->post(route('api.users.update-avatar', $managedUser), [
                'avatar_file' => UploadedFile::fake()->image('new-avatar.jpg', 100, 100),
            ]);

        $response->assertOk();
        $response->assertJsonPath('message', 'Avatar actualizado correctamente.');
        $this->assertStringContainsString('.jpg', (string) $response->json('data.avatar_url'));

        Storage::disk('public')->assertMissing($legacyPath);
        Storage::disk('public')->assertExists('users/avatars/' . $managedUser->id . '/new-avatar.jpg');
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
