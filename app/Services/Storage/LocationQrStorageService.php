<?php

namespace App\Services\Storage;

use App\Models\Location;

class LocationQrStorageService
{
    public function __construct(private DomainStorageService $domainStorage)
    {
    }

    public function replaceQrImage(Location $location, string $pngContents): string
    {
        return $this->domainStorage->replaceContents(
            'locations',
            $location->qr_image_url,
            $this->domainStorage->pathPrefix('locations'),
            $location->id . '.png',
            $pngContents
        );
    }
}
