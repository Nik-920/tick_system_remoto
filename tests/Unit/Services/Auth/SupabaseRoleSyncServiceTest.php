<?php

namespace Tests\Unit\Services\Auth;

use App\Models\User;
use App\Services\Auth\SupabaseRoleSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SupabaseRoleSyncServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_user_role_is_skipped_when_feature_is_disabled(): void
    {
        config([
            'services.supabase.role_sync_enabled' => false,
            'services.supabase.url' => 'https://supabase.test',
            'services.supabase.service_role_key' => 'service-role-key',
        ]);

        Http::fake();

        $user = $this->createUserWithRole('admin');

        $service = app(SupabaseRoleSyncService::class);
        $result = $service->syncUserRole($user);

        $this->assertSame('skipped', $result['status']);
        $this->assertSame('admin', $result['role']);

        Http::assertNothingSent();
    }

    public function test_sync_user_role_calls_supabase_when_enabled_and_configured(): void
    {
        config([
            'services.supabase.role_sync_enabled' => true,
            'services.supabase.url' => 'https://supabase.test',
            'services.supabase.service_role_key' => 'service-role-key',
            'services.supabase.auth_admin_users_endpoint' => '/auth/v1/admin/users',
            'services.supabase.timeout' => 10,
        ]);

        Http::fake([
            'https://supabase.test/auth/v1/admin/users/*' => Http::response(['ok' => true], 200),
        ]);

        $user = $this->createUserWithRole('maintenance');

        $service = app(SupabaseRoleSyncService::class);
        $result = $service->syncUserRole($user);

        $this->assertSame('synced', $result['status']);
        $this->assertSame('maintenance', $result['role']);

        Http::assertSent(function (Request $request) use ($user): bool {
            return $request->method() === 'PUT'
                && $request->url() === 'https://supabase.test/auth/v1/admin/users/'.rawurlencode($user->id)
                && $request->hasHeader('apikey', 'service-role-key')
                && $request->hasHeader('Authorization', 'Bearer service-role-key')
                && ($request['app_metadata']['app_role'] ?? null) === 'maintenance';
        });
    }

    public function test_sync_all_users_roles_returns_summary(): void
    {
        config([
            'services.supabase.role_sync_enabled' => false,
            'services.supabase.url' => 'https://supabase.test',
            'services.supabase.service_role_key' => 'service-role-key',
        ]);

        $this->createUserWithRole('admin');
        $this->createUserWithRole('reporter');

        $service = app(SupabaseRoleSyncService::class);
        $summary = $service->syncAllUsersRoles();

        $this->assertSame([
            'synced' => 0,
            'failed' => 0,
            'skipped' => 2,
        ], $summary);
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
