<?php

declare(strict_types=1);

namespace App\Services\Community;

final readonly class RemoteImagePolicy
{
    /**
     * @param  list<string>  $allowedHosts  Lowercase hostnames permitted for remote fetch.
     * @param  string  $requiredPathPrefix  Expected URL path prefix (e.g. storage/v1/object/public/BUCKET/).
     * @param  int  $maxBytes  Maximum response body size in bytes.
     * @param  int  $timeoutSeconds  HTTP request timeout.
     */
    public function __construct(
        public array $allowedHosts,
        public string $requiredPathPrefix,
        public int $maxBytes,
        public int $timeoutSeconds = 5,
    ) {}
}
