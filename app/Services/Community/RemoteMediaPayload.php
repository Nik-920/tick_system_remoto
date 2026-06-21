<?php

declare(strict_types=1);

namespace App\Services\Community;

final readonly class RemoteMediaPayload
{
    public function __construct(
        public string $bytes,
        public string $contentType,
    ) {}
}
