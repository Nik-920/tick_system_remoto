<?php

namespace App\Services\Qr;

use App\Models\Location;
use Illuminate\Support\Facades\Storage;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class QrImageService
{
    public function generateAndStore(Location $location): string
    {
        $scanUrl = route('scan.show', ['token' => $location->qr_token]);
        $png = QrCode::format('png')
            ->size(350)
            ->margin(1)
            ->generate($scanUrl);

        $path = $this->pathForLocation($location->id);
        Storage::disk('public')->put($path, $png);

        return Storage::disk('public')->url($path);
    }

    private function pathForLocation(string $locationId): string
    {
        return "qr-codes/{$locationId}.png";
    }
}
