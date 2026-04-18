<?php

namespace Tests\Unit\Services\Observability;

use App\Services\Observability\SentrySanitizer;
use Tests\TestCase;

class SentrySanitizerTest extends TestCase
{
    public function test_sanitizer_hashes_and_redacts_sensitive_values(): void
    {
        $payload = [
            'email' => 'student@example.com',
            'token' => 'secret-token',
            'description' => 'Texto sensible',
            'title' => 'Titulo sensible',
            'nested' => [
                'qr_token' => 'qr-secret',
                'comment' => 'Comentario sensible',
            ],
        ];

        $sanitized = SentrySanitizer::sanitizeArray($payload);

        $this->assertSame(hash('sha256', 'student@example.com'), $sanitized['email']);
        $this->assertSame(hash('sha256', 'secret-token'), $sanitized['token']);
        $this->assertSame('[REDACTED]', $sanitized['description']);
        $this->assertSame('[REDACTED]', $sanitized['title']);
        $this->assertSame(hash('sha256', 'qr-secret'), $sanitized['nested']['qr_token']);
        $this->assertSame('[REDACTED]', $sanitized['nested']['comment']);
    }

    public function test_sanitizer_limits_long_strings_and_redacts_headers(): void
    {
        $payload = [
            'Authorization' => 'Bearer abc123',
            'Cookie' => 'session=secret',
            'details' => str_repeat('x', 1200),
        ];

        $sanitized = SentrySanitizer::sanitizeArray($payload);

        $this->assertSame('[REDACTED]', $sanitized['Authorization']);
        $this->assertSame('[REDACTED]', $sanitized['Cookie']);
        $this->assertIsString($sanitized['details']);
        $this->assertTrue(strlen($sanitized['details']) <= 503);
    }
}
