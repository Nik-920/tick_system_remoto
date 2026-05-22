<?php

namespace Tests\Feature\Jobs;

use App\Jobs\GenerateLocationQrImage;
use App\Models\Location;
use App\Services\Observability\TicketQrLogger;
use App\Services\Qr\QrImageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class GenerateLocationQrImageTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_marks_failed_when_location_missing_token(): void
    {
        $location = $this->createLocation(['qr_token' => '', 'qr_job_id' => 'job-1']);

        $job = new GenerateLocationQrImage($location->id, 'job-1', 'corr-1');

        $job->handle($this->fakeQrService(), $this->makeLogger());

        $location->refresh();

        $this->assertSame('failed', $location->qr_generation_status);
        $this->assertNotEmpty($location->qr_last_error);
        $this->assertNull($location->qr_generated_at);
    }

    public function test_job_sets_ready_status_and_url_on_success(): void
    {
        $location = $this->createLocation(['qr_token' => 'token-1', 'qr_job_id' => 'job-2']);

        $job = new GenerateLocationQrImage($location->id, 'job-2', 'corr-2');

        $job->handle($this->fakeQrService('https://example.test/qr.png'), $this->makeLogger());

        $location->refresh();

        $this->assertSame('ready', $location->qr_generation_status);
        $this->assertSame('https://example.test/qr.png', $location->qr_image_url);
        $this->assertNull($location->qr_last_error);
        $this->assertNotNull($location->qr_generated_at);
    }

    public function test_job_ignores_stale_job(): void
    {
        $location = $this->createLocation([
            'qr_token' => 'token-2',
            'qr_job_id' => 'job-current',
            'qr_generation_status' => 'pending',
        ]);

        $job = new GenerateLocationQrImage($location->id, 'job-old', 'corr-3');

        $job->handle($this->fakeQrService('https://example.test/qr.png'), $this->makeLogger());

        $location->refresh();

        $this->assertSame('pending', $location->qr_generation_status);
        $this->assertNull($location->qr_image_url);
    }

    public function test_job_marks_failed_and_rethrows_on_exception(): void
    {
        $location = $this->createLocation(['qr_token' => 'token-3', 'qr_job_id' => 'job-3']);

        $job = new GenerateLocationQrImage($location->id, 'job-3', 'corr-4');

        try {
            $job->handle($this->fakeQrServiceThrowing(), $this->makeLogger());
            $this->fail('Expected exception was not thrown.');
        } catch (RuntimeException $exception) {
            $location->refresh();

            $this->assertSame('failed', $location->qr_generation_status);
            $this->assertNotEmpty($location->qr_last_error);
        }
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createLocation(array $overrides = []): Location
    {
        return Location::create(array_merge([
            'name' => 'Aula Test',
            'building' => 'Edificio A',
            'floor' => '1',
            'room_code' => 'A-101',
            'qr_token' => 'token-default',
            'is_active' => true,
        ], $overrides));
    }

    private function fakeQrService(string $url = 'https://example.test/qr.png'): QrImageService
    {
        return new class($url) extends QrImageService
        {
            public function __construct(private string $url) {}

            public function generateAndStore(Location $location): string
            {
                return $this->url;
            }
        };
    }

    private function fakeQrServiceThrowing(): QrImageService
    {
        return new class extends QrImageService
        {
            public function __construct() {}

            public function generateAndStore(Location $location): string
            {
                throw new RuntimeException('qr generation failed');
            }
        };
    }

    private function makeLogger(): TicketQrLogger
    {
        return new class extends TicketQrLogger
        {
            /**
             * @param  array<string, mixed>  $context
             */
            public function info(string $eventName, array $context = []): void {}

            /**
             * @param  array<string, mixed>  $context
             */
            public function warning(string $eventName, array $context = []): void {}

            /**
             * @param  array<string, mixed>  $context
             */
            public function error(string $eventName, array $context = []): void {}
        };
    }
}
