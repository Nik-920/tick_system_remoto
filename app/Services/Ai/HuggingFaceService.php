<?php

namespace App\Services\Ai;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class HuggingFaceService
{
    /**
     * @var array<string, mixed>
     */
    private array $config;

    private bool $enabled;

    public function __construct()
    {
        $this->config = (array) config('ai.huggingface');
        $this->enabled = (bool) config('ai.enabled') && (bool) ($this->config['enabled'] ?? false);
    }

    public function embedding(string $text, ?string $model = null): array
    {
        $model = $model ?: (string) ($this->config['embedding_model'] ?? '');
        $data = $this->postToFeatureExtractionModel($model, [
            'inputs' => $text,
            'options' => [
                'wait_for_model' => $this->waitForModel(),
            ],
        ]);

        return $this->extractVector($data);
    }

    public function classifyZeroShot(string $text, array $labels, ?string $model = null): array
    {
        $labels = array_values(array_filter($labels, static fn ($label) => $label !== ''));
        if ($labels === []) {
            return [
                'labels' => [],
                'scores' => [],
                'sequence' => null,
            ];
        }

        $model = $model ?: (string) ($this->config['classification_model'] ?? '');
        $data = $this->postToClassificationModel($model, [
            'inputs' => $text,
            'parameters' => [
                'candidate_labels' => $labels,
            ],
            'options' => [
                'wait_for_model' => $this->waitForModel(),
            ],
        ]);

        if (array_is_list($data)) {
            $labelsFromResponse = [];
            $scoresFromResponse = [];

            foreach ($data as $item) {
                if (! is_array($item)) {
                    continue;
                }

                if (isset($item['label']) && is_string($item['label'])) {
                    $labelsFromResponse[] = $item['label'];
                }

                if (isset($item['score']) && is_numeric($item['score'])) {
                    $scoresFromResponse[] = (float) $item['score'];
                }
            }

            return [
                'labels' => $labelsFromResponse,
                'scores' => $scoresFromResponse,
                'sequence' => $text,
            ];
        }

        return [
            'labels' => $data['labels'] ?? [],
            'scores' => array_map(static fn ($score): float => (float) $score, $data['scores'] ?? []),
            'sequence' => $data['sequence'] ?? $text,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>|array<int, mixed>
     */
    private function postToFeatureExtractionModel(string $model, array $payload): array
    {
        return $this->post($model, '/models/'.$model.'/pipeline/feature-extraction', $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>|array<int, mixed>
     */
    private function postToClassificationModel(string $model, array $payload): array
    {
        return $this->post($model, '/models/'.$model, $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>|array<int, mixed>
     */
    private function post(string $model, string $route, array $payload): array
    {
        $this->ensureEnabled();

        if ($model === '') {
            throw new RuntimeException('Hugging Face model is not configured.');
        }

        try {
            $response = $this->client()->post($route, $payload);
        } catch (RequestException $exception) {
            $response = $exception->response;
            $body = is_object($response) && method_exists($response, 'body') ? $response->body() : $exception->getMessage();

            throw new RuntimeException('Hugging Face request failed: '.$body, previous: $exception);
        }

        if (! $response->successful()) {
            throw new RuntimeException('Hugging Face request failed: '.$response->body());
        }

        $json = $response->json();
        if (! is_array($json)) {
            throw new RuntimeException('Unexpected Hugging Face response.');
        }

        if (array_key_exists('error', $json)) {
            $message = is_string($json['error']) ? $json['error'] : 'Unknown Hugging Face error.';
            throw new RuntimeException('Hugging Face error: '.$message);
        }

        return $json;
    }

    private function client(): PendingRequest
    {
        $baseUrl = rtrim((string) ($this->config['base_url'] ?? ''), '/');
        $apiKey = (string) ($this->config['api_key'] ?? '');

        if ($apiKey === '') {
            throw new RuntimeException('Hugging Face API key is not configured.');
        }

        return Http::baseUrl($baseUrl)
            ->withToken($apiKey)
            ->acceptJson()
            ->asJson()
            ->timeout((int) ($this->config['timeout_seconds'] ?? 30))
            ->connectTimeout((int) ($this->config['connect_timeout_seconds'] ?? 10))
            ->retry((int) ($this->config['retries'] ?? 0), 200);
    }

    private function ensureEnabled(): void
    {
        if (! $this->enabled) {
            throw new RuntimeException('Hugging Face integration is disabled.');
        }
    }

    private function waitForModel(): bool
    {
        $value = $this->config['wait_for_model'] ?? true;
        $parsed = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        return $parsed ?? (bool) $value;
    }

    /**
     * @param  array<string, mixed>|array<int, mixed>  $data
     * @return array<int, float>
     */
    private function extractVector(array $data): array
    {
        if (isset($data[0]) && is_array($data[0])) {
            $data = $data[0];
        }

        return array_map(static fn ($value) => (float) $value, $data);
    }
}
