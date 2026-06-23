<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Community;

use App\Services\Community\RemoteImageFetcher;
use App\Services\Community\RemoteImagePolicy;
use App\Services\Community\RemoteImageUrlGuard;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Unit tests for RemoteImageFetcher — HTTP download and payload validation.
 *
 * Uses Http::fake() to avoid real network calls.
 * Covers all branches in fetch(), fetchAndValidate(), and buildPayload().
 */
class RemoteImageFetcherTest extends TestCase
{
    private RemoteImageFetcher $fetcher;

    private RemoteImagePolicy $policy;

    private const VALID_URL = 'https://test.supabase.co/storage/v1/object/public/TicketCategoria/icons/x.jpg';

    protected function setUp(): void
    {
        parent::setUp();

        $this->fetcher = new RemoteImageFetcher(new RemoteImageUrlGuard);
        $this->policy = new RemoteImagePolicy(
            allowedHosts: ['test.supabase.co'],
            requiredPathPrefix: 'storage/v1/object/public/TicketCategoria/',
            maxBytes: 100,
            timeoutSeconds: 5,
        );
    }

    // ── allows() ──────────────────────────────────────────────────────────────

    public function test_allows_returns_true_for_valid_url(): void
    {
        $this->assertTrue($this->fetcher->allows(self::VALID_URL, $this->policy));
    }

    public function test_allows_returns_false_for_http_url(): void
    {
        $url = str_replace('https://', 'http://', self::VALID_URL);

        $this->assertFalse($this->fetcher->allows($url, $this->policy));
    }

    public function test_allows_returns_false_for_non_allowlisted_host(): void
    {
        $url = 'https://evil.example.com/storage/v1/object/public/TicketCategoria/icons/x.jpg';

        $this->assertFalse($this->fetcher->allows($url, $this->policy));
    }

    // ── fetch(): URL guard blocks before HTTP ─────────────────────────────────

    public function test_fetch_returns_null_for_disallowed_url_without_http_call(): void
    {
        Http::preventStrayRequests();

        $result = $this->fetcher->fetch('https://evil.com/icon.jpg', $this->policy);

        $this->assertNull($result);
    }

    // ── fetch(): HTTP response validation ─────────────────────────────────────

    public function test_fetch_returns_payload_for_200_image_jpeg(): void
    {
        Http::fake([self::VALID_URL => Http::response('FAKEJPEG', 200, ['Content-Type' => 'image/jpeg'])]);

        $result = $this->fetcher->fetch(self::VALID_URL, $this->policy);

        $this->assertNotNull($result);
        $this->assertSame('image/jpeg', $result->contentType);
        $this->assertSame('FAKEJPEG', $result->bytes);
    }

    public function test_fetch_returns_payload_for_200_image_png(): void
    {
        Http::fake([self::VALID_URL => Http::response('FAKEPNG', 200, ['Content-Type' => 'image/png'])]);

        $result = $this->fetcher->fetch(self::VALID_URL, $this->policy);

        $this->assertNotNull($result);
        $this->assertSame('image/png', $result->contentType);
    }

    public function test_fetch_strips_charset_from_content_type(): void
    {
        Http::fake([self::VALID_URL => Http::response('DATA', 200, ['Content-Type' => 'image/jpeg; charset=UTF-8'])]);

        $result = $this->fetcher->fetch(self::VALID_URL, $this->policy);

        $this->assertNotNull($result);
        $this->assertSame('image/jpeg', $result->contentType);
    }

    public function test_fetch_returns_null_for_404_response(): void
    {
        Http::fake([self::VALID_URL => Http::response('', 404)]);

        $this->assertNull($this->fetcher->fetch(self::VALID_URL, $this->policy));
    }

    public function test_fetch_returns_null_for_500_response(): void
    {
        Http::fake([self::VALID_URL => Http::response('error', 500)]);

        $this->assertNull($this->fetcher->fetch(self::VALID_URL, $this->policy));
    }

    public function test_fetch_returns_null_for_301_redirect(): void
    {
        // withoutRedirecting() means 3xx is treated as a non-successful response
        Http::fake([self::VALID_URL => Http::response('', 301, ['Location' => 'https://other.example.com'])]);

        $this->assertNull($this->fetcher->fetch(self::VALID_URL, $this->policy));
    }

    // ── fetch(): Content-Type validation ──────────────────────────────────────

    public function test_fetch_returns_null_for_html_content_type(): void
    {
        Http::fake([self::VALID_URL => Http::response('<html>evil</html>', 200, ['Content-Type' => 'text/html'])]);

        $this->assertNull($this->fetcher->fetch(self::VALID_URL, $this->policy));
    }

    public function test_fetch_returns_null_for_json_content_type(): void
    {
        Http::fake([self::VALID_URL => Http::response('{}', 200, ['Content-Type' => 'application/json'])]);

        $this->assertNull($this->fetcher->fetch(self::VALID_URL, $this->policy));
    }

    public function test_fetch_returns_null_for_empty_content_type(): void
    {
        Http::fake([self::VALID_URL => Http::response('DATA', 200, ['Content-Type' => ''])]);

        $this->assertNull($this->fetcher->fetch(self::VALID_URL, $this->policy));
    }

    // ── fetch(): Size validation ───────────────────────────────────────────────

    public function test_fetch_returns_null_when_content_length_exceeds_max(): void
    {
        Http::fake([self::VALID_URL => Http::response('x', 200, [
            'Content-Type' => 'image/jpeg',
            'Content-Length' => '200', // > maxBytes=100
        ])]);

        $this->assertNull($this->fetcher->fetch(self::VALID_URL, $this->policy));
    }

    public function test_fetch_returns_null_when_body_exceeds_max_without_content_length_header(): void
    {
        $oversizedBody = str_repeat('X', 101); // > maxBytes=100
        Http::fake([self::VALID_URL => Http::response($oversizedBody, 200, ['Content-Type' => 'image/jpeg'])]);

        $this->assertNull($this->fetcher->fetch(self::VALID_URL, $this->policy));
    }

    public function test_fetch_returns_null_for_empty_body(): void
    {
        Http::fake([self::VALID_URL => Http::response('', 200, ['Content-Type' => 'image/jpeg'])]);

        $this->assertNull($this->fetcher->fetch(self::VALID_URL, $this->policy));
    }

    public function test_fetch_allows_body_exactly_at_max_bytes(): void
    {
        $exactBody = str_repeat('A', 100); // == maxBytes=100
        Http::fake([self::VALID_URL => Http::response($exactBody, 200, ['Content-Type' => 'image/jpeg'])]);

        $result = $this->fetcher->fetch(self::VALID_URL, $this->policy);

        $this->assertNotNull($result);
        $this->assertSame($exactBody, $result->bytes);
    }

    // ── fetch(): Exception handling ───────────────────────────────────────────

    public function test_fetch_returns_null_on_connection_exception(): void
    {
        Http::fake(static function () {
            throw new ConnectionException('Connection refused');
        });

        $this->assertNull($this->fetcher->fetch(self::VALID_URL, $this->policy));
    }

    public function test_fetch_returns_null_on_generic_throwable(): void
    {
        Http::fake(static function () {
            throw new \RuntimeException('Unexpected error');
        });

        $this->assertNull($this->fetcher->fetch(self::VALID_URL, $this->policy));
    }
}
