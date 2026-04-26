<?php

namespace App\Jobs;

use App\Models\Location;
use App\Services\Observability\TicketQrLogger;
use App\Services\Qr\QrImageService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;
use Throwable;

class GenerateLocationQrImage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public string $locationId,
        public ?string $jobTrackingId = null,
        public string $correlationId = '',
    ) {}

    public function handle(QrImageService $qrImageService, TicketQrLogger $logger): void
    {
        $startedAt = microtime(true);

        $logger->info('qr.generation.started', [
            'location_id' => $this->locationId,
            'qr_job_id' => $this->jobTrackingId,
            'correlation_id' => $this->correlationId,
        ]);

        $location = Location::query()->find($this->locationId);
        if ($location === null) {
            $logger->warning('qr.generation.location_not_found', [
                'location_id' => $this->locationId,
                'qr_job_id' => $this->jobTrackingId,
                'correlation_id' => $this->correlationId,
            ]);

            return;
        }

        if ($this->jobTrackingId !== null && $location->qr_job_id !== $this->jobTrackingId) {
            $logger->info('qr.generation.stale_job_ignored', [
                'location_id' => $location->id,
                'qr_job_id' => $this->jobTrackingId,
                'correlation_id' => $this->correlationId,
                'current_qr_job_id' => $location->qr_job_id,
            ]);

            return;
        }

        if ($location->qr_token === null || $location->qr_token === '') {
            $location->forceFill([
                'qr_generation_status' => 'failed',
                'qr_last_error' => 'No existe qr_token para generar el codigo QR.',
                'qr_generated_at' => null,
            ])->save();

            $logger->warning('qr.generation.failed_missing_token', [
                'location_id' => $location->id,
                'qr_job_id' => $this->jobTrackingId,
                'correlation_id' => $this->correlationId,
                'qr_generation_status' => 'failed',
            ]);

            return;
        }

        $location->forceFill([
            'qr_generation_status' => 'processing',
            'qr_last_error' => null,
        ])->save();

        $logger->info('qr.generation.processing', [
            'location_id' => $location->id,
            'qr_job_id' => $this->jobTrackingId,
            'correlation_id' => $this->correlationId,
            'qr_generation_status' => 'processing',
        ]);

        try {
            $url = $qrImageService->generateAndStore($location);

            $location->forceFill([
                'qr_image_url' => $url,
                'qr_generation_status' => 'ready',
                'qr_last_error' => null,
                'qr_generated_at' => now(),
            ])->save();

            $logger->info('qr.generation.ready', [
                'location_id' => $location->id,
                'qr_job_id' => $this->jobTrackingId,
                'correlation_id' => $this->correlationId,
                'qr_generation_status' => 'ready',
                'duration_ms' => $this->elapsedMilliseconds($startedAt),
            ]);
        } catch (Throwable $exception) {
            $location->forceFill([
                'qr_generation_status' => 'failed',
                'qr_last_error' => Str::limit($exception->getMessage(), 1000, ''),
                'qr_generated_at' => null,
            ])->save();

            $logger->error('qr.generation.failed', [
                'location_id' => $location->id,
                'qr_job_id' => $this->jobTrackingId,
                'correlation_id' => $this->correlationId,
                'qr_generation_status' => 'failed',
                'exception_class' => $exception::class,
                'error_message' => Str::limit($exception->getMessage(), 500, ''),
                'duration_ms' => $this->elapsedMilliseconds($startedAt),
            ]);

            throw $exception;
        }
    }

    private function elapsedMilliseconds(float $startedAt): int
    {
        return (int) round((microtime(true) - $startedAt) * 1000);
    }
}
