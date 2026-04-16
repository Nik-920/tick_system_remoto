<?php

namespace Tests\Feature\Web;

use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QrScanControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_qr_token_redirects_to_ticket_create_with_prefilled_location(): void
    {
        $user = User::factory()->create();
        $location = $this->createLocation('qr-aula-301-token', true);

        $response = $this
            ->actingAs($user)
            ->get(route('scan.show', ['token' => $location->qr_token]));

        $response->assertRedirect(route('tickets.create', ['location_id' => $location->id]));
        $response->assertSessionHas('status', 'Ubicacion detectada desde QR: ' . $location->name);
    }

    public function test_invalid_qr_token_returns_not_found(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get(route('scan.show', ['token' => 'bad']));

        $response->assertNotFound();
    }

    public function test_inactive_location_qr_token_returns_not_found(): void
    {
        $user = User::factory()->create();
        $location = $this->createLocation('qr-aula-302-token', false);

        $response = $this
            ->actingAs($user)
            ->get(route('scan.show', ['token' => $location->qr_token]));

        $response->assertNotFound();
    }

    public function test_qr_scan_route_is_rate_limited_to_20_requests_per_minute(): void
    {
        $user = User::factory()->create();
        $location = $this->createLocation('qr-aula-303-token', true);

        for ($i = 0; $i < 20; $i++) {
            $response = $this
                ->actingAs($user)
                ->get(route('scan.show', ['token' => $location->qr_token]));

            $response->assertStatus(302);
        }

        $blockedResponse = $this
            ->actingAs($user)
            ->get(route('scan.show', ['token' => $location->qr_token]));

        $blockedResponse->assertStatus(429);
    }

    private function createLocation(string $token, bool $isActive): Location
    {
        return Location::create([
            'name' => 'Aula 301',
            'building' => 'Edificio C',
            'floor' => '3',
            'room_code' => 'C-301-' . substr($token, -3),
            'qr_token' => $token,
            'is_active' => $isActive,
        ]);
    }
}
