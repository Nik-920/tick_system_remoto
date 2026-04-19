<?php

namespace Tests\Unit\Services\Qr;

use App\Models\Location;
use App\Services\Qr\QrImageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Tests\TestCase;

class QrImageServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_generate_and_store_uses_locations_domain_storage(): void
    {
        config([
            'filesystems.domain_disks.locations' => 'public',
            'filesystems.domain_prefixes.locations' => 'locations/qr-codes',
        ]);
        Storage::fake('public');

        $location = Location::query()->create([
            'name' => 'Aula Qr Test',
            'building' => 'Edificio A',
            'floor' => '1',
            'room_code' => 'A-100',
            'qr_token' => 'qr-token-test-100',
            'is_active' => true,
        ]);

        QrCode::swap(new class
        {
            public function format(string $format): self
            {
                return $this;
            }

            public function size(int $size): self
            {
                return $this;
            }

            public function margin(int $margin): self
            {
                return $this;
            }

            public function generate(string $content): string
            {
                return 'mock-png-content';
            }
        });

        $service = app(QrImageService::class);
        $url = $service->generateAndStore($location);

        $expectedPath = 'locations/qr-codes/' . $location->id . '.png';
        $this->assertStringContainsString('/storage/' . $expectedPath, $url);
        Storage::disk('public')->assertExists($expectedPath);
    }
}
