<?php

namespace Tests\Unit\Architecture;

use App\Contracts\Ai\Duplicates\DuplicateDetectionStrategy;
use App\Services\Ai\Duplicates\DuplicateDetectionEngine;
use Illuminate\Support\Facades\File;
use ReflectionClass;
use Tests\TestCase;

/**
 * Architectural test for Strategy Pattern — Duplicate Detection.
 *
 * Validates the structural invariants of the pattern implementation:
 * 1. All concrete strategies implement DuplicateDetectionStrategy.
 * 2. DetectDuplicates does not import concrete strategy classes.
 * 3. DuplicateDetectionEngine depends only on the Strategy contract.
 * 4. AppServiceProvider uses tag() for strategy registration.
 * 5. Concrete strategies are NOT listed in DetectDuplicates.
 */
final class StrategyPatternDuplicateDetectionTest extends TestCase
{
    // ── Criterion 1: All strategies implement the contract ────────────────────

    public function test_all_concrete_strategies_implement_duplicate_detection_strategy(): void
    {
        $strategiesPath = base_path('app/Services/Ai/Duplicates/Strategies');

        $this->assertDirectoryExists($strategiesPath);

        foreach (File::allFiles($strategiesPath) as $file) {
            $class = 'App\\Services\\Ai\\Duplicates\\Strategies\\'.$file->getFilenameWithoutExtension();
            $this->assertTrue(class_exists($class), "Class {$class} should be loadable.");

            $reflection = new ReflectionClass($class);
            $this->assertTrue(
                $reflection->implementsInterface(DuplicateDetectionStrategy::class),
                "{$class} must implement DuplicateDetectionStrategy"
            );
        }
    }

    // ── Criterion 2: DetectDuplicates does not reference concrete strategies ──

    public function test_detect_duplicates_job_does_not_import_concrete_strategies(): void
    {
        $contents = File::get(app_path('Jobs/DetectDuplicates.php'));

        $this->assertStringNotContainsString(
            'Services\\Ai\\Duplicates\\Strategies\\',
            $contents,
            'DetectDuplicates must not import concrete strategy classes. '.
            'It should depend only on DuplicateDetectionEngine.'
        );
    }

    // ── Criterion 3: Engine depends on contract, not concretes ───────────────

    public function test_duplicate_detection_engine_does_not_import_concrete_strategies(): void
    {
        $contents = File::get(app_path('Services/Ai/Duplicates/DuplicateDetectionEngine.php'));

        $this->assertStringNotContainsString(
            'Services\\Ai\\Duplicates\\Strategies\\',
            $contents,
            'DuplicateDetectionEngine must not import concrete strategy classes.'
        );
    }

    public function test_duplicate_detection_engine_references_strategy_contract(): void
    {
        $contents = File::get(app_path('Services/Ai/Duplicates/DuplicateDetectionEngine.php'));

        $this->assertStringContainsString(
            'DuplicateDetectionStrategy',
            $contents,
            'DuplicateDetectionEngine must depend on the DuplicateDetectionStrategy contract.'
        );
    }

    // ── Criterion 4: AppServiceProvider uses tag() for registration ───────────

    public function test_app_service_provider_uses_tag_for_strategy_registration(): void
    {
        $contents = File::get(app_path('Providers/AppServiceProvider.php'));

        $this->assertStringContainsString(
            'duplicate.detection.strategies',
            $contents,
            'AppServiceProvider must register strategies via tag(\'duplicate.detection.strategies\').'
        );
    }

    public function test_app_service_provider_binds_duplicate_detection_engine(): void
    {
        $contents = File::get(app_path('Providers/AppServiceProvider.php'));

        $this->assertStringContainsString(
            'DuplicateDetectionEngine::class',
            $contents,
            'AppServiceProvider must bind DuplicateDetectionEngine to the container.'
        );
    }

    // ── Criterion 5: Engine is resolvable from container ─────────────────────

    public function test_duplicate_detection_engine_resolves_from_container(): void
    {
        $engine = app(DuplicateDetectionEngine::class);

        $this->assertInstanceOf(DuplicateDetectionEngine::class, $engine);
    }

    // ── Criterion 6: Strategy interface has evaluate() method ─────────────────

    public function test_strategy_interface_defines_evaluate_method(): void
    {
        $reflection = new ReflectionClass(DuplicateDetectionStrategy::class);

        $this->assertTrue(
            $reflection->hasMethod('evaluate'),
            'DuplicateDetectionStrategy interface must declare evaluate().'
        );

        $method = $reflection->getMethod('evaluate');
        $this->assertCount(1, $method->getParameters(), 'evaluate() must have exactly one parameter.');
        $this->assertSame('context', $method->getParameters()[0]->getName());
    }

    // ── Criterion 7: Adding a strategy only requires registration, not job edit

    public function test_all_concrete_strategies_are_tagged_in_provider(): void
    {
        $providerContents = File::get(base_path('app/Providers/AppServiceProvider.php'));
        $strategiesPath = base_path('app/Services/Ai/Duplicates/Strategies');

        foreach (File::allFiles($strategiesPath) as $file) {
            $className = $file->getFilenameWithoutExtension();

            $this->assertStringContainsString(
                $className.'::class',
                $providerContents,
                "Strategy {$className} must be tagged in AppServiceProvider."
            );
        }
    }
}
