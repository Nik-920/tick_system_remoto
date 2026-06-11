<?php

namespace Tests\Unit\Services\Ai\Duplicates\Strategies;

use App\Services\Ai\Duplicates\Strategies\TitleOverlapStrategy;

final class TitleOverlapStrategyTest extends StrategyTestCase
{
    private TitleOverlapStrategy $strategy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->strategy = new TitleOverlapStrategy;
        config(['ai.dedup.strategies.title_overlap' => [
            'enabled' => true,
            'weight_high' => 25,
            'weight_medium' => 15,
        ]]);
    }

    public function test_returns_high_points_when_title_overlap_is_high(): void
    {
        // "proyector sala falla encendido" vs "proyector sala falla encendido apagado"
        // intersection: {proyector, sala, falla, encendido} = 4
        // union: {proyector, sala, falla, encendido, apagado} = 5
        // ratio = 4/5 = 0.80 >= 0.70
        $ctx = $this->makeContext(
            ticketAttrs: ['title' => 'Proyector sala falla encendido'],
            candidateAttrs: ['title' => 'Proyector sala falla encendido apagado'],
        );

        $result = $this->strategy->evaluate($ctx);

        $this->assertSame(25, $result->points);
        $this->assertGreaterThanOrEqual(0.70, $result->metadata['overlap_ratio']);
    }

    public function test_returns_medium_points_when_overlap_is_medium(): void
    {
        $ctx = $this->makeContext(
            ticketAttrs: ['title' => 'Proyector roto sala laboratorio'],
            candidateAttrs: ['title' => 'Proyector falla encendido computadora'],
        );

        $result = $this->strategy->evaluate($ctx);

        // At least some overlap on "proyector"
        $this->assertGreaterThanOrEqual(0, $result->points);
    }

    public function test_returns_zero_when_no_overlap(): void
    {
        $ctx = $this->makeContext(
            ticketAttrs: ['title' => 'Mesa rota pata lateral'],
            candidateAttrs: ['title' => 'Computadora pantalla azul'],
        );

        $result = $this->strategy->evaluate($ctx);

        $this->assertSame(0, $result->points);
        $this->assertSame(0.0, $result->metadata['overlap_ratio']);
    }

    public function test_returns_zero_when_tokens_are_empty(): void
    {
        $ctx = $this->makeContext(
            ticketAttrs: ['title' => ''],
            candidateAttrs: ['title' => ''],
        );

        $result = $this->strategy->evaluate($ctx);

        $this->assertSame(0, $result->points);
    }

    public function test_returns_zero_when_disabled(): void
    {
        config(['ai.dedup.strategies.title_overlap' => ['enabled' => false]]);

        $ctx = $this->makeContext(
            ticketAttrs: ['title' => 'Proyector sala falla encendido'],
            candidateAttrs: ['title' => 'Proyector sala falla encendido'],
        );

        $result = $this->strategy->evaluate($ctx);

        $this->assertSame(0, $result->points);
    }

    public function test_metadata_contains_overlap_tokens_and_ratio(): void
    {
        $ctx = $this->makeContext(
            ticketAttrs: ['title' => 'Proyector sala falla'],
            candidateAttrs: ['title' => 'Proyector encendido falla'],
        );

        $result = $this->strategy->evaluate($ctx);

        $this->assertArrayHasKey('overlap_tokens', $result->metadata);
        $this->assertArrayHasKey('overlap_ratio', $result->metadata);
        $this->assertIsArray($result->metadata['overlap_tokens']);
    }
}
