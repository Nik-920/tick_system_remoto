<?php

namespace Tests\Unit\Services\Users;

use App\Models\User;
use App\Services\Users\UserRoleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserRoleServiceTest extends TestCase
{
    use RefreshDatabase;

    private UserRoleService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensureRolesExist();
        $this->service = new UserRoleService;
    }

    public function test_assign_single_role_replaces_previous_roles(): void
    {
        $user = User::factory()->create();
        $user->assignRole('reporter');

        $this->service->assignSingleRole($user, 'admin');

        $this->assertTrue($user->hasRole('admin'));
        $this->assertFalse($user->hasRole('reporter'));
        $this->assertCount(1, $user->getRoleNames());
    }

    public function test_is_self_demotion_only_when_actor_lowers_own_super_admin(): void
    {
        $actor = $this->userWithRole('super_admin');
        $other = $this->userWithRole('reporter');

        $this->assertTrue($this->service->isSelfDemotion($actor, $actor, 'admin'));
        $this->assertFalse($this->service->isSelfDemotion($actor, $actor, 'super_admin'));
        $this->assertFalse($this->service->isSelfDemotion($actor, $other, 'reporter'));
    }

    public function test_would_remove_last_super_admin_by_demotion(): void
    {
        $onlySuper = $this->userWithRole('super_admin');

        // Único super_admin → degradarlo deja al sistema sin ninguno.
        $this->assertTrue($this->service->wouldRemoveLastSuperAdminByDemotion($onlySuper, 'admin'));
        // Mantener super_admin nunca es un problema.
        $this->assertFalse($this->service->wouldRemoveLastSuperAdminByDemotion($onlySuper, 'super_admin'));

        // Con dos super_admins, degradar a uno es seguro.
        $this->userWithRole('super_admin');
        $this->assertFalse($this->service->wouldRemoveLastSuperAdminByDemotion($onlySuper, 'admin'));
    }

    public function test_would_remove_last_super_admin_by_deletion(): void
    {
        $onlySuper = $this->userWithRole('super_admin');
        $this->assertTrue($this->service->wouldRemoveLastSuperAdminByDeletion($onlySuper));

        $this->userWithRole('super_admin');
        $this->assertFalse($this->service->wouldRemoveLastSuperAdminByDeletion($onlySuper));

        $reporter = $this->userWithRole('reporter');
        $this->assertFalse($this->service->wouldRemoveLastSuperAdminByDeletion($reporter));
    }

    public function test_count_super_admins(): void
    {
        $this->assertSame(0, $this->service->countSuperAdmins());

        $this->userWithRole('super_admin');
        $this->userWithRole('super_admin');
        $this->userWithRole('admin');

        $this->assertSame(2, $this->service->countSuperAdmins());
    }

    public function test_status_message_appends_sync_message_when_not_synced(): void
    {
        $synced = ['status' => 'synced', 'message' => 'irrelevante', 'role' => 'admin'];
        $failed = ['status' => 'failed', 'message' => 'Supabase no respondió.', 'role' => 'admin'];

        $this->assertSame('Base.', $this->service->statusMessage('Base.', $synced));
        $this->assertSame('Base. Supabase no respondió.', $this->service->statusMessage('Base.', $failed));
    }

    private function userWithRole(string $role): User
    {
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
