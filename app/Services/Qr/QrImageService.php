<?php

namespace App\Services\Qr;

use App\Models\Location;
use App\Services\Storage\LocationQrStorageService;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class QrImageService
{
    public function __construct(private LocationQrStorageService $qrStorageService)
    {
    }

    public function generateAndStore(Location $location): string
    {
        $scanUrl = route('scan.show', ['token' => $location->qr_token]);
        $png = QrCode::format('png')
            ->size(350)
            ->margin(1)
            ->generate($scanUrl);

        return $this->qrStorageService->replaceQrImage($location, $png);
    }
}
