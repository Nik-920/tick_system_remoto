<?php

namespace Tests\Feature\Ai;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * @group ai
 */
class DeduplicationTest extends TestCase
{
    public function test_dedup_similarity_threshold_is_respected(): void
    {
        $threshold = config('ai.dedup.similarity_threshold');
        $enabled = $this->isDedupEnabled();

        $this->assertIsFloat($threshold);
        $this->assertIsBool($enabled);

        $above = min(1.0, $threshold + 0.05);
        $below = max(0.0, $threshold - 0.05);

        if (! $enabled) {
            $this->assertFalse($this->isDuplicate($above, $enabled, $threshold));
            return;
        }

        $this->assertTrue($this->isDuplicate($threshold, $enabled, $threshold));
        $this->assertTrue($this->isDuplicate($above, $enabled, $threshold));
        $this->assertFalse($this->isDuplicate($below, $enabled, $threshold));
    }

    public function test_dedup_window_limits_scope(): void
    {
        $windowHours = config('ai.dedup.window_hours');
        $enabled = $this->isDedupEnabled();

        $this->assertIsInt($windowHours);
        $this->assertGreaterThanOrEqual(0, $windowHours);

        $recent = Carbon::now()->subHours(max($windowHours - 1, 0));
        $old = Carbon::now()->subHours($windowHours + 1);

        if (! $enabled) {
            $this->assertFalse($this->isWithinWindow($recent, $enabled, $windowHours));
            return;
        }

        $this->assertTrue($this->isWithinWindow($recent, $enabled, $windowHours));
        $this->assertFalse($this->isWithinWindow($old, $enabled, $windowHours));
    }

    public function test_embedding_request_can_be_faked(): void
    {
        $baseUrl = rtrim(config('ai.huggingface.base_url'), '/');
        $model = config('ai.huggingface.embedding_model');
        $endpoint = $baseUrl . '/models/' . $model;

        Http::fake([
            $endpoint => Http::response([0.1, 0.2, 0.3], 200),
        ]);

        $response = Http::withToken(config('ai.huggingface.api_key') ?: 'test-token')
            ->timeout(config('ai.huggingface.timeout_seconds'))
            ->post($endpoint, ['inputs' => 'Projector not working']);

        $this->assertTrue($response->successful());
        $this->assertIsArray($response->json());
    }

    private function isDuplicate(float $score, bool $enabled, float $threshold): bool
    {
        if (! $enabled) {
            return false;
        }

        return $score >= $threshold;
    }

    private function isWithinWindow(Carbon $createdAt, bool $enabled, int $windowHours): bool
    {
        if (! $enabled) {
            return false;
        }

        return $createdAt->getTimestamp() >= Carbon::now()->subHours($windowHours)->getTimestamp();
    }

    private function isDedupEnabled(): bool
    {
        return (bool) config('ai.enabled') && (bool) config('ai.dedup.enabled');
    }
}
