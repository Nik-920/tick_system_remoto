<?php

namespace Tests\Feature;

use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    public function test_default_security_headers_are_added(): void
    {
        $response = $this->get('/up');

        $response->assertOk();
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->assertHeader('Permissions-Policy', 'camera=(), geolocation=(), microphone=()');
        $response->assertHeader('Cross-Origin-Opener-Policy', 'same-origin');
        $response->assertHeader('Cross-Origin-Resource-Policy', 'same-origin');
        $response->assertHeader('Content-Security-Policy-Report-Only');
        $response->assertHeaderMissing('Content-Security-Policy');
        $response->assertHeaderMissing('Strict-Transport-Security');
    }

    public function test_csp_header_switches_to_enforce_mode_when_configured(): void
    {
        config([
            'security_headers.csp_mode' => 'enforce',
            'security_headers.csp' => "default-src 'self'; frame-ancestors 'self'",
        ]);

        $response = $this->get('/up');

        $response->assertOk();
        $response->assertHeader('Content-Security-Policy', "default-src 'self'; frame-ancestors 'self'");
        $response->assertHeaderMissing('Content-Security-Policy-Report-Only');
    }

    public function test_hsts_header_is_added_for_forwarded_https_requests(): void
    {
        config([
            'security_headers.hsts_enabled' => true,
            'security_headers.hsts_force' => false,
            'security_headers.hsts_max_age' => 63072000,
            'security_headers.hsts_include_subdomains' => true,
            'security_headers.hsts_preload' => true,
        ]);

        $response = $this->get('/up', ['X-Forwarded-Proto' => 'https']);

        $response->assertOk();
        $response->assertHeader('Strict-Transport-Security', 'max-age=63072000; includeSubDomains; preload');
    }
}
