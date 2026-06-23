<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Community;

use App\Services\Community\RemoteImagePolicy;
use App\Services\Community\RemoteImageUrlGuard;
use Tests\TestCase;

/**
 * Unit tests for RemoteImageUrlGuard — SSRF validation rules.
 *
 * Each test exercises a specific guard clause to ensure coverage > 80%
 * on the new Community remote-image infrastructure.
 */
class RemoteImageUrlGuardTest extends TestCase
{
    private RemoteImageUrlGuard $guard;

    private RemoteImagePolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->guard = new RemoteImageUrlGuard;
        $this->policy = new RemoteImagePolicy(
            allowedHosts: ['test.supabase.co'],
            requiredPathPrefix: 'storage/v1/object/public/TicketCategoria/',
            maxBytes: 2 * 1024 * 1024,
        );
    }

    // ── Allowed ───────────────────────────────────────────────────────────────

    public function test_allows_valid_https_supabase_url(): void
    {
        $url = 'https://test.supabase.co/storage/v1/object/public/TicketCategoria/categories/icons/x.jpg';

        $this->assertTrue($this->guard->allows($url, $this->policy));
    }

    public function test_allows_url_with_query_string(): void
    {
        $url = 'https://test.supabase.co/storage/v1/object/public/TicketCategoria/icons/x.jpg?v=1';

        $this->assertTrue($this->guard->allows($url, $this->policy));
    }

    // ── Scheme ────────────────────────────────────────────────────────────────

    public function test_blocks_http_scheme(): void
    {
        $url = 'http://test.supabase.co/storage/v1/object/public/TicketCategoria/icons/x.jpg';

        $this->assertFalse($this->guard->allows($url, $this->policy));
    }

    public function test_blocks_javascript_scheme(): void
    {
        $this->assertFalse($this->guard->allows('javascript:alert(1)', $this->policy));
    }

    public function test_blocks_data_uri(): void
    {
        $this->assertFalse($this->guard->allows('data:image/png;base64,abc123', $this->policy));
    }

    public function test_blocks_file_uri(): void
    {
        $this->assertFalse($this->guard->allows('file:///etc/passwd', $this->policy));
    }

    // ── Malformed URL ─────────────────────────────────────────────────────────

    public function test_blocks_empty_string(): void
    {
        $this->assertFalse($this->guard->allows('', $this->policy));
    }

    public function test_blocks_random_string_without_scheme(): void
    {
        $this->assertFalse($this->guard->allows('not a url at all', $this->policy));
    }

    // ── Userinfo ──────────────────────────────────────────────────────────────

    public function test_blocks_url_with_user_and_password(): void
    {
        $url = 'https://user:pass@test.supabase.co/storage/v1/object/public/TicketCategoria/icons/x.jpg';

        $this->assertFalse($this->guard->allows($url, $this->policy));
    }

    public function test_blocks_url_with_username_only(): void
    {
        $url = 'https://user@test.supabase.co/storage/v1/object/public/TicketCategoria/icons/x.jpg';

        $this->assertFalse($this->guard->allows($url, $this->policy));
    }

    // ── Host allowlist ────────────────────────────────────────────────────────

    public function test_blocks_non_allowlisted_host(): void
    {
        $url = 'https://evil.example.com/storage/v1/object/public/TicketCategoria/icons/x.jpg';

        $this->assertFalse($this->guard->allows($url, $this->policy));
    }

    public function test_blocks_subdomain_of_allowlisted_host(): void
    {
        $url = 'https://sub.test.supabase.co/storage/v1/object/public/TicketCategoria/icons/x.jpg';

        $this->assertFalse($this->guard->allows($url, $this->policy));
    }

    // ── Loopback / private IPs ────────────────────────────────────────────────

    public function test_blocks_localhost(): void
    {
        $policy = new RemoteImagePolicy(['localhost'], 'storage/', 1024);

        $this->assertFalse($this->guard->allows('https://localhost/storage/x.jpg', $policy));
    }

    public function test_blocks_127_0_0_1(): void
    {
        $policy = new RemoteImagePolicy(['127.0.0.1'], 'storage/', 1024);

        $this->assertFalse($this->guard->allows('https://127.0.0.1/storage/x.jpg', $policy));
    }

    public function test_blocks_ipv6_loopback(): void
    {
        $policy = new RemoteImagePolicy(['::1'], 'storage/', 1024);

        $this->assertFalse($this->guard->allows('https://[::1]/storage/x.jpg', $policy));
    }

    public function test_blocks_10_x_private_ip(): void
    {
        $policy = new RemoteImagePolicy(['10.0.0.1'], 'storage/', 1024);

        $this->assertFalse($this->guard->allows('https://10.0.0.1/storage/x.jpg', $policy));
    }

    public function test_blocks_192_168_x_private_ip(): void
    {
        $policy = new RemoteImagePolicy(['192.168.1.1'], 'storage/', 1024);

        $this->assertFalse($this->guard->allows('https://192.168.1.1/storage/x.jpg', $policy));
    }

    public function test_blocks_172_16_x_private_ip(): void
    {
        $policy = new RemoteImagePolicy(['172.16.0.1'], 'storage/', 1024);

        $this->assertFalse($this->guard->allows('https://172.16.0.1/storage/x.jpg', $policy));
    }

    public function test_blocks_172_31_x_private_ip(): void
    {
        $policy = new RemoteImagePolicy(['172.31.0.1'], 'storage/', 1024);

        $this->assertFalse($this->guard->allows('https://172.31.0.1/storage/x.jpg', $policy));
    }

    public function test_blocks_172_20_x_private_ip(): void
    {
        $policy = new RemoteImagePolicy(['172.20.0.1'], 'storage/', 1024);

        $this->assertFalse($this->guard->allows('https://172.20.0.1/storage/x.jpg', $policy));
    }

    public function test_allows_172_15_x_which_is_not_private(): void
    {
        // 172.15.x is outside the private range 172.16–172.31
        $policy = new RemoteImagePolicy(['172.15.0.1'], 'storage/', 1024);

        $this->assertTrue($this->guard->allows('https://172.15.0.1/storage/x.jpg', $policy));
    }

    public function test_blocks_169_254_link_local(): void
    {
        $policy = new RemoteImagePolicy(['169.254.0.1'], 'storage/', 1024);

        $this->assertFalse($this->guard->allows('https://169.254.0.1/storage/x.jpg', $policy));
    }

    public function test_blocks_100_64_cgnat(): void
    {
        $policy = new RemoteImagePolicy(['100.64.0.1'], 'storage/', 1024);

        $this->assertFalse($this->guard->allows('https://100.64.0.1/storage/x.jpg', $policy));
    }

    public function test_blocks_0_0_0_0(): void
    {
        $policy = new RemoteImagePolicy(['0.0.0.0'], 'storage/', 1024);

        $this->assertFalse($this->guard->allows('https://0.0.0.0/storage/x.jpg', $policy));
    }

    public function test_blocks_zero_dot_prefix_address(): void
    {
        $policy = new RemoteImagePolicy(['0.1.2.3'], 'storage/', 1024);

        $this->assertFalse($this->guard->allows('https://0.1.2.3/storage/x.jpg', $policy));
    }

    // ── Path / bucket guard ───────────────────────────────────────────────────

    public function test_blocks_wrong_bucket_in_path(): void
    {
        $url = 'https://test.supabase.co/storage/v1/object/public/OTHER_BUCKET/categories/icons/x.jpg';

        $this->assertFalse($this->guard->allows($url, $this->policy));
    }

    public function test_blocks_path_not_matching_prefix_at_all(): void
    {
        $url = 'https://test.supabase.co/other/arbitrary/path/icon.jpg';

        $this->assertFalse($this->guard->allows($url, $this->policy));
    }

    public function test_blocks_empty_allowed_hosts_list(): void
    {
        $policy = new RemoteImagePolicy(
            allowedHosts: [],
            requiredPathPrefix: 'storage/v1/object/public/TicketCategoria/',
            maxBytes: 2 * 1024 * 1024,
        );

        $url = 'https://test.supabase.co/storage/v1/object/public/TicketCategoria/icons/x.jpg';

        $this->assertFalse($this->guard->allows($url, $policy));
    }
}
