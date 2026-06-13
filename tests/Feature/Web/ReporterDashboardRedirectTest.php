<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ReporterDashboardRedirectTest extends TestCase
{
    use RefreshDatabase;

    public function test_reporter_is_redirected_to_reporter_dashboard_after_login(): void
    {
        $reporter = $this->userWithRole('reporter');

        $response = $this->post(route('login'), [
            'email' => $reporter->email,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('reporter.dashboard'));
    }

    public function test_authenticated_reporter_visiting_login_is_redirected_to_reporter_dashboard(): void
    {
        $reporter = $this->userWithRole('reporter');

        $this->actingAs($reporter)
            ->get(route('login'))
            ->assertRedirect(route('reporter.dashboard'));
    }

    public function test_authenticated_reporter_visiting_legacy_dashboard_redirects_to_reporter_dashboard(): void
    {
        $reporter = $this->userWithRole('reporter');

        $this->actingAs($reporter)
            ->get(route('dashboard.index'))
            ->assertRedirect(route('reporter.dashboard'));
    }

    public function test_admin_is_not_redirected_to_reporter_dashboard_after_login(): void
    {
        $admin = $this->userWithRole('admin');

        $response = $this->post(route('login'), [
            'email' => $admin->email,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('dashboard.index'));
    }

    public function test_admin_can_access_dashboard_index_without_redirect(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->get(route('dashboard.index'))
            ->assertOk()
            ->assertViewIs('dashboard.admin');
    }

    public function test_maintenance_is_not_redirected_to_reporter_dashboard_after_login(): void
    {
        $maintenance = $this->userWithRole('maintenance');

        $response = $this->post(route('login'), [
            'email' => $maintenance->email,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('dashboard.index'));
    }

    public function test_maintenance_can_access_dashboard_index_without_redirect(): void
    {
        $maintenance = $this->userWithRole('maintenance');

        $this->actingAs($maintenance)
            ->get(route('dashboard.index'))
            ->assertOk()
            ->assertViewIs('dashboard.maintenance-v2');
    }

    public function test_super_admin_can_access_dashboard_index_without_redirect(): void
    {
        $superAdmin = $this->userWithRole('super_admin');

        $this->actingAs($superAdmin)
            ->get(route('dashboard.index'))
            ->assertOk()
            ->assertViewIs('dashboard.admin');
    }

    private function userWithRole(string $role): User
    {
        foreach (['reporter', 'maintenance', 'admin', 'super_admin'] as $roleName) {
            Role::findOrCreate($roleName, 'web');
        }

        $user = User::factory()->create(['password' => bcrypt('password')]);
        $user->assignRole($role);

        return $user;
    }
}
