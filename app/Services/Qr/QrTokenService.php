<?php

namespace App\Services\Qr;

use App\Models\Location;
use RuntimeException;

class QrTokenService
{
    public function generateUniqueToken(int $maxAttempts = 10): string
    {
        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            $token = $this->generateToken();

            $exists = Location::query()
                ->where('qr_token', $token)
                ->exists();

            if (! $exists) {
                return $token;
            }
        }

        throw new RuntimeException('No fue posible generar un qr_token unico.');
    }

    private function generateToken(): string
    {
        $raw = base64_encode(random_bytes(24));

        return rtrim(strtr($raw, '+/', '-_'), '=');
    }
}
