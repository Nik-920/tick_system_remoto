<?php

namespace Tests\Feature;

use App\Services\Health\HealthCheckService;
use Tests\TestCase;

class HealthControllerTest extends TestCase
{
    public function test_health_endpoint_is_public_and_returns_expected_structure(): void
    {
        $response = $this->getJson('/health');

        $response->assertOk();
        $response->assertJsonPath('status', 'healthy');
        $response->assertJsonStructure([
            'status',
            'timestamp',
            'checks' => [
                'database' => ['status', 'connection', 'latency_ms'],
                'queue' => ['status', 'driver', 'latency_ms'],
            ],
        ]);
    }

    public function test_health_endpoint_returns_503_when_any_check_fails(): void
    {
        $this->app->instance(HealthCheckService::class, new class () extends HealthCheckService {
            public function check(): array
            {
                return [
                    'status' => 'unhealthy',
                    'timestamp' => now()->toIso8601String(),
                    'checks' => [
                        'database' => [
                            'status' => 'failed',
                            'connection' => 'sqlite',
                            'latency_ms' => 1,
                            'message' => 'No se pudo verificar la conexion de base de datos.',
                        ],
                        'queue' => [
                            'status' => 'ok',
                            'driver' => 'sync',
                            'latency_ms' => 0,
                        ],
                    ],
                ];
            }
        });

        $response = $this->getJson('/health');

        $response->assertStatus(503);
        $response->assertJsonPath('status', 'unhealthy');
        $response->assertJsonPath('checks.database.status', 'failed');
    }
}
