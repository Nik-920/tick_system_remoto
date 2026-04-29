<?php

namespace Tests\Unit\Services;

use App\Services\Ai\HuggingFaceService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class HuggingFaceServiceTest extends TestCase
{
    public function test_embedding_request_uses_token_and_expected_endpoint(): void
    {
        config([
            'ai.enabled' => true,
            'ai.huggingface.enabled' => true,
            'ai.huggingface.api_key' => 'hf_test_token_123',
            'ai.huggingface.base_url' => 'https://router.huggingface.co/hf-inference',
            'ai.huggingface.embedding_model' => 'thenlper/gte-large',
            'ai.huggingface.wait_for_model' => true,
            'ai.huggingface.timeout_seconds' => 30,
            'ai.huggingface.connect_timeout_seconds' => 10,
            'ai.huggingface.retries' => 2,
        ]);

        Http::fake(function (Request $request) {
            $this->assertSame('https://router.huggingface.co/hf-inference/models/thenlper/gte-large/pipeline/feature-extraction', $request->url());
            $this->assertSame('Bearer hf_test_token_123', $request->header('Authorization')[0] ?? null);

            $payload = $request->data();
            $this->assertSame('Prueba de conectividad', $payload['inputs'] ?? null);
            $this->assertTrue($payload['options']['wait_for_model'] ?? false);

            return Http::response([[0.12, 0.34, 0.56]], 200);
        });

        $service = new HuggingFaceService;
        $vector = $service->embedding('Prueba de conectividad');

        $this->assertSame([0.12, 0.34, 0.56], $vector);
    }

    public function test_embedding_request_404_is_reported_as_runtime_exception(): void
    {
        config([
            'ai.enabled' => true,
            'ai.huggingface.enabled' => true,
            'ai.huggingface.api_key' => 'hf_test_token_123',
            'ai.huggingface.base_url' => 'https://router.huggingface.co/hf-inference',
            'ai.huggingface.embedding_model' => 'thenlper/gte-large',
        ]);

        Http::fake([
            'https://router.huggingface.co/hf-inference/models/thenlper/gte-large/pipeline/feature-extraction' => Http::response('Not Found', 404),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Hugging Face request failed: Not Found');

        $service = new HuggingFaceService;
        $service->embedding('Prueba de conectividad');
    }
}
