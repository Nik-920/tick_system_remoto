<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LocationFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_location_search_does_not_default_is_active_to_inactive(): void
    {
        $admin = $this->userWithRole('admin');

        $active = $this->location('Laboratorio Activo', 'A-001', true);
        $inactive = $this->location('Laboratorio Inactivo', 'A-002', false);

        $response = $this->actingAs($admin)
            ->get(route('locations.index', ['search' => 'Laboratorio', 'is_active' => '']));

        $response->assertOk();
        $response->assertSeeText($active->name);
        $response->assertSeeText($inactive->name);
    }

    public function test_location_filter_by_active_shows_only_active(): void
    {
        $admin = $this->userWithRole('admin');

        $active = $this->location('Lab Activo', 'B-001', true);
        $inactive = $this->location('Lab Inactivo', 'B-002', false);

        $response = $this->actingAs($admin)
            ->get(route('locations.index', ['search' => 'Lab', 'is_active' => '1']));

        $response->assertOk();
        $response->assertSeeText($active->name);
        $response->assertDontSeeText($inactive->name);
    }

    public function test_location_filter_by_inactive_shows_only_inactive(): void
    {
        $admin = $this->userWithRole('admin');

        $active = $this->location('Sala Activa', 'C-001', true);
        $inactive = $this->location('Sala Inactiva', 'C-002', false);

        $response = $this->actingAs($admin)
            ->get(route('locations.index', ['search' => 'Sala', 'is_active' => '0']));

        $response->assertOk();
        $response->assertDontSeeText($active->name);
        $response->assertSeeText($inactive->name);
    }

    public function test_omitting_is_active_shows_all_locations(): void
    {
        $admin = $this->userWithRole('admin');

        $active = $this->location('Aula Activa', 'D-001', true);
        $inactive = $this->location('Aula Inactiva', 'D-002', false);

        $response = $this->actingAs($admin)
            ->get(route('locations.index', ['search' => 'Aula']));

        $response->assertOk();
        $response->assertSeeText($active->name);
        $response->assertSeeText($inactive->name);
    }

    public function test_is_active_select_shows_todas_when_filter_is_empty(): void
    {
        $admin = $this->userWithRole('admin');

        $response = $this->actingAs($admin)
            ->get(route('locations.index', ['search' => 'test', 'is_active' => '']));

        $response->assertOk();
        // The "Todas" option with value="" must be present and selected (no value="0" selected).
        $response->assertSee('value=""', false);
        $response->assertDontSee('value="0" selected', false);
    }

    public function test_pagination_preserves_search_and_is_active_filters(): void
    {
        $admin = $this->userWithRole('admin');

        // Create 11 active locations matching the search so a second page is generated.
        for ($i = 1; $i <= 11; $i++) {
            $this->location("Lab Sala {$i}", "LAB-{$i}", true);
        }

        $response = $this->actingAs($admin)
            ->get(route('locations.index', ['search' => 'Lab', 'is_active' => '1', 'per_page' => '10']));

        $response->assertOk();
        // Pagination links must carry the filter query string.
        $response->assertSee('search=Lab', false);
        $response->assertSee('is_active=1', false);
    }

    private function location(string $name, string $roomCode, bool $isActive): Location
    {
        return Location::create([
            'name' => $name,
            'building' => 'Edificio Test',
            'floor' => '1',
            'room_code' => $roomCode,
            'qr_token' => 'qr-'.Str::uuid()->toString(),
            'is_active' => $isActive,
        ]);
    }

    private function userWithRole(string $role): User
    {
        foreach (['reporter', 'maintenance', 'admin', 'super_admin'] as $roleName) {
            Role::findOrCreate($roleName, 'web');
        }

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }
}
