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

    public function test_zero_shot_classification_request_uses_expected_endpoint(): void
    {
        config([
            'ai.enabled' => true,
            'ai.huggingface.enabled' => true,
            'ai.huggingface.api_key' => 'hf_test_token_123',
            'ai.huggingface.base_url' => 'https://router.huggingface.co/hf-inference',
            'ai.huggingface.classification_model' => 'facebook/bart-large-mnli',
            'ai.huggingface.wait_for_model' => true,
        ]);

        Http::fake(function (Request $request) {
            $this->assertSame('https://router.huggingface.co/hf-inference/models/facebook/bart-large-mnli', $request->url());
            $this->assertSame('Bearer hf_test_token_123', $request->header('Authorization')[0] ?? null);

            $payload = $request->data();
            $this->assertSame('Necesito un reembolso', $payload['inputs'] ?? null);
            $this->assertSame(['refund', 'legal', 'faq'], $payload['parameters']['candidate_labels'] ?? null);
            $this->assertTrue($payload['options']['wait_for_model'] ?? false);

            return Http::response([
                'labels' => ['refund', 'legal', 'faq'],
                'scores' => [0.91, 0.06, 0.03],
                'sequence' => 'Necesito un reembolso',
            ], 200);
        });

        $service = new HuggingFaceService;
        $result = $service->classifyZeroShot('Necesito un reembolso', ['refund', 'legal', 'faq']);

        $this->assertSame(['refund', 'legal', 'faq'], $result['labels']);
        $this->assertSame([0.91, 0.06, 0.03], $result['scores']);
        $this->assertSame('Necesito un reembolso', $result['sequence']);
    }
}
