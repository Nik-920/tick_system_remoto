<?php

namespace App\Jobs;

use App\Models\Location;
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
    )
    {
    }

    public function handle(QrImageService $qrImageService): void
    {
        $location = Location::query()->find($this->locationId);
        if ($location === null) {
            return;
        }

        if ($this->jobTrackingId !== null && $location->qr_job_id !== $this->jobTrackingId) {
            return;
        }

        if ($location->qr_token === null || $location->qr_token === '') {
            $location->forceFill([
                'qr_generation_status' => 'failed',
                'qr_last_error' => 'No existe qr_token para generar el codigo QR.',
                'qr_generated_at' => null,
            ])->save();

            return;
        }

        $location->forceFill([
            'qr_generation_status' => 'processing',
            'qr_last_error' => null,
        ])->save();

        try {
            $url = $qrImageService->generateAndStore($location);

            $location->forceFill([
                'qr_image_url' => $url,
                'qr_generation_status' => 'ready',
                'qr_last_error' => null,
                'qr_generated_at' => now(),
            ])->save();
        } catch (Throwable $exception) {
            $location->forceFill([
                'qr_generation_status' => 'failed',
                'qr_last_error' => Str::limit($exception->getMessage(), 1000, ''),
                'qr_generated_at' => null,
            ])->save();

            throw $exception;
        }
    }
}
