<?php

namespace Tests\Unit\Services;

use Tests\TestCase;

/**
 * @group ai
 */
class AiConfigTest extends TestCase
{
    public function test_ai_config_has_expected_sections(): void
    {
        $config = config('ai');

        $this->assertIsArray($config);
        $this->assertArrayHasKey('enabled', $config);
        $this->assertArrayHasKey('huggingface', $config);
        $this->assertArrayHasKey('dedup', $config);
        $this->assertArrayHasKey('recurrence', $config);
        $this->assertArrayHasKey('automation', $config);
    }

    public function test_ai_config_values_have_expected_types(): void
    {
        $this->assertIsBool(config('ai.enabled'));
        $this->assertIsBool(config('ai.dedup.enabled'));
        $this->assertIsBool(config('ai.recurrence.enabled'));
        $this->assertIsBool(config('ai.automation.auto_classify'));
        $this->assertIsBool(config('ai.automation.auto_priority'));
        $this->assertIsBool(config('ai.automation.async_processing'));

        $this->assertIsFloat(config('ai.dedup.similarity_threshold'));
        $this->assertIsInt(config('ai.dedup.window_hours'));
        $this->assertIsInt(config('ai.recurrence.window_hours'));

        $baseUrl = config('ai.huggingface.base_url');
        $this->assertIsString($baseUrl);
        $this->assertNotSame('', $baseUrl);
        $this->assertStringContainsString('router.huggingface.co/hf-inference', $baseUrl);
    }
}
