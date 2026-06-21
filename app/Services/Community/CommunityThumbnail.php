<?php

declare(strict_types=1);

namespace App\Services\Community;

final readonly class CommunityThumbnail
{
    public function __construct(
        public string $disk,
        public string $path,
        public string $contentType,
    ) {}
}
