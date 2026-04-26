<?php

namespace Tests\Unit\Services;

use App\Services\Health\HealthCheckService;
use Tests\TestCase;

class HealthCheckServiceTest extends TestCase
{
    public function test_check_returns_healthy_when_database_and_queue_are_available(): void
    {
        $service = new HealthCheckService;

        $result = $service->check();

        $this->assertSame('healthy', $result['status']);
        $this->assertSame('ok', $result['checks']['database']['status']);
        $this->assertSame('ok', $result['checks']['queue']['status']);
        $this->assertArrayHasKey('timestamp', $result);
    }

    public function test_check_returns_unhealthy_when_database_check_fails(): void
    {
        config(['database.default' => 'missing_connection']);

        $service = new HealthCheckService;

        $result = $service->check();

        $this->assertSame('unhealthy', $result['status']);
        $this->assertSame('failed', $result['checks']['database']['status']);
        $this->assertSame('ok', $result['checks']['queue']['status']);
    }

    public function test_check_returns_unhealthy_when_queue_check_fails(): void
    {
        config(['queue.default' => 'missing_driver']);

        $service = new HealthCheckService;

        $result = $service->check();

        $this->assertSame('unhealthy', $result['status']);
        $this->assertSame('ok', $result['checks']['database']['status']);
        $this->assertSame('failed', $result['checks']['queue']['status']);
    }
}
