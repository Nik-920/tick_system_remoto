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
            'services.supabase.storage.domain_buckets.locations' => 'TablaLocations',
            'services.supabase.storage.use_local_disk_for_testing' => true,
            'services.supabase.storage.testing_disk' => 'public',
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

        QrCode::swap(new class () {
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

        $expectedPath = 'locations/qr-codes/'.$location->id.'.png';
        $this->assertStringContainsString('/storage/v1/object/public/TablaLocations/'.$expectedPath, $url);
        Storage::disk('public')->assertExists($expectedPath);
    }

    public function test_generate_and_store_falls_back_to_svg_when_png_backend_is_unavailable(): void
    {
        config([
            'services.supabase.storage.domain_buckets.locations' => 'TablaLocations',
            'services.supabase.storage.use_local_disk_for_testing' => true,
            'services.supabase.storage.testing_disk' => 'public',
            'filesystems.domain_prefixes.locations' => 'locations/qr-codes',
        ]);
        Storage::fake('public');

        $location = Location::query()->create([
            'name' => 'Aula Qr Svg',
            'building' => 'Edificio B',
            'floor' => '2',
            'room_code' => 'B-200',
            'qr_token' => 'qr-token-svg-200',
            'is_active' => true,
        ]);

        QrCode::swap(new class () {
            private string $currentFormat = 'png';

            public function format(string $format): self
            {
                $this->currentFormat = $format;

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
                if ($this->currentFormat === 'png') {
                    throw new \RuntimeException('You need to install the imagick extension to use this back end');
                }

                return '<svg>mock-svg-content</svg>';
            }
        });

        $service = app(QrImageService::class);
        $url = $service->generateAndStore($location);

        $expectedPath = 'locations/qr-codes/'.$location->id.'.svg';
        $this->assertStringContainsString('/storage/v1/object/public/TablaLocations/'.$expectedPath, $url);
        Storage::disk('public')->assertExists($expectedPath);
    }
}
