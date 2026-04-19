<?php

namespace Tests\Feature\Console;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SyncSupabaseRolesCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_displays_summary_table(): void
    {
        config([
            'services.supabase.role_sync_enabled' => false,
            'services.supabase.url' => 'https://supabase.test',
            'services.supabase.service_role_key' => 'service-role-key',
        ]);

        $this->createUserWithRole('admin');
        $this->createUserWithRole('reporter');

        $this->artisan('app:sync-supabase-roles')
            ->expectsOutput('Sincronizacion de roles finalizada.')
            ->expectsTable(['Estado', 'Cantidad'], [
                ['synced', 0],
                ['failed', 0],
                ['skipped', 2],
            ])
            ->assertExitCode(0);
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
