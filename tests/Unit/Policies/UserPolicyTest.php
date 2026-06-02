<?php

namespace Tests\Unit\Policies;

use App\Models\User;
use App\Policies\UserPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_has_full_access_except_self_delete(): void
    {
        $superAdmin = $this->createUserWithRole('super_admin');
        $otherUser = $this->createUserWithRole('reporter');

        $policy = new UserPolicy();

        $this->assertTrue($policy->viewAny($superAdmin));
        $this->assertTrue($policy->view($superAdmin, $otherUser));
        $this->assertTrue($policy->create($superAdmin));
        $this->assertTrue($policy->update($superAdmin, $otherUser));
        $this->assertTrue($policy->assignRole($superAdmin, $otherUser));
        $this->assertTrue($policy->delete($superAdmin, $otherUser));
        $this->assertFalse($policy->delete($superAdmin, $superAdmin));
    }

    public function test_non_super_admin_is_denied_access(): void
    {
        $admin = $this->createUserWithRole('admin');
        $managedUser = $this->createUserWithRole('reporter');

        $policy = new UserPolicy();

        $this->assertFalse($policy->viewAny($admin));
        $this->assertFalse($policy->view($admin, $managedUser));
        $this->assertFalse($policy->create($admin));
        $this->assertFalse($policy->update($admin, $managedUser));
        $this->assertFalse($policy->assignRole($admin, $managedUser));
        $this->assertFalse($policy->delete($admin, $managedUser));
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
