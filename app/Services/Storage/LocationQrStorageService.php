<?php

namespace App\Services\Storage;

use App\Models\Location;

class LocationQrStorageService
{
    public function __construct(private DomainStorageService $domainStorage) {}

    public function replaceQrImage(
        Location $location,
        string $contents,
        string $extension = 'png',
        string $contentType = 'application/octet-stream'
    ): string {
        $normalizedExtension = ltrim(strtolower(trim($extension)), '.');
        if ($normalizedExtension === '') {
            $normalizedExtension = 'png';
        }

        return $this->domainStorage->replaceContents(
            'locations',
            $location->qr_image_url,
            $this->domainStorage->pathPrefix('locations'),
            $location->id.'.'.$normalizedExtension,
            $contents,
            $contentType
        );
    }

    public function deleteQrImage(?string $qrImageUrl): void
    {
        $this->domainStorage->deleteManagedUrl('locations', $qrImageUrl);
    }
}
