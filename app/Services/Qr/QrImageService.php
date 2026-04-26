<?php

namespace App\Services\Qr;

use App\Models\Location;
use App\Services\Storage\LocationQrStorageService;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Throwable;

class QrImageService
{
    public function __construct(private LocationQrStorageService $qrStorageService) {}

    public function generateAndStore(Location $location): string
    {
        $scanUrl = route('scan.show', ['token' => $location->qr_token]);

        try {
            $png = QrCode::format('png')
                ->size(350)
                ->margin(1)
                ->generate($scanUrl);

            return $this->qrStorageService->replaceQrImage(
                $location,
                (string) $png,
                'png',
                'image/png'
            );
        } catch (Throwable $exception) {
            if (! $this->isImagickDependencyError($exception)) {
                throw $exception;
            }

            $svg = QrCode::format('svg')
                ->size(350)
                ->margin(1)
                ->generate($scanUrl);

            return $this->qrStorageService->replaceQrImage(
                $location,
                (string) $svg,
                'svg',
                'image/svg+xml'
            );
        }
    }

    private function isImagickDependencyError(Throwable $exception): bool
    {
        return str_contains(strtolower($exception->getMessage()), 'imagick');
    }
}
