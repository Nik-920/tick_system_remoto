<?php

namespace Tests\Unit\Services;

use App\Services\Health\HealthCheckService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
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
        $this->assertArrayHasKey('redis', $result['checks']);
        $this->assertArrayHasKey('timestamp', $result);
    }

    public function test_check_always_includes_redis_key_in_checks(): void
    {
        $service = new HealthCheckService;

        $result = $service->check();

        $this->assertArrayHasKey('redis', $result['checks']);
        $this->assertArrayHasKey('status', $result['checks']['redis']);
        $this->assertArrayHasKey('latency_ms', $result['checks']['redis']);
        $this->assertArrayHasKey('required', $result['checks']['redis']);
    }

    public function test_check_redis_optional_does_not_affect_overall_health(): void
    {
        config(['app.redis_health_required' => false]);
        // Use sync driver so checkQueue() does not call Redis and interfere with
        // this test's redis-optional behaviour assertion.
        config(['queue.default' => 'sync']);

        Redis::shouldReceive('connection')
            ->with('default')
            ->andReturnSelf();
        Redis::shouldReceive('ping')
            ->andReturn('ERR');

        $service = new HealthCheckService;
        $result = $service->check();

        // Redis check fails but it is not required — overall health unchanged by redis alone.
        $this->assertSame('failed', $result['checks']['redis']['status']);
        $this->assertFalse($result['checks']['redis']['required']);
        // Database and queue are still ok, so overall is healthy.
        $this->assertSame('ok', $result['checks']['database']['status']);
        $this->assertSame('healthy', $result['status']);
    }

    public function test_check_redis_required_makes_unhealthy_when_redis_fails(): void
    {
        config(['app.redis_health_required' => true]);

        Redis::shouldReceive('connection')
            ->with('default')
            ->andReturnSelf();
        Redis::shouldReceive('ping')
            ->andReturn('ERR');

        $service = new HealthCheckService;
        $result = $service->check();

        $this->assertSame('failed', $result['checks']['redis']['status']);
        $this->assertTrue($result['checks']['redis']['required']);
        $this->assertSame('unhealthy', $result['status']);
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

    public function test_check_redis_queue_successful(): void
    {
        config(['queue.default' => 'redis']);
        config(['queue.connections.redis.connection' => 'default']);

        Redis::shouldReceive('connection')
            ->with('default')
            ->andReturnSelf();
        Redis::shouldReceive('ping')
            ->andReturn('+PONG');

        Queue::shouldReceive('connection')
            ->with('redis')
            ->andReturnSelf();
        Queue::shouldReceive('size')
            ->andReturn(0);

        $service = new HealthCheckService;
        $result = $service->check();

        $this->assertSame('healthy', $result['status']);
        $this->assertSame('ok', $result['checks']['queue']['status']);
        $this->assertSame('redis', $result['checks']['queue']['driver']);
    }

    public function test_check_redis_queue_fails_ping(): void
    {
        config(['queue.default' => 'redis']);
        config(['queue.connections.redis.connection' => 'default']);

        Redis::shouldReceive('connection')
            ->with('default')
            ->andReturnSelf();
        Redis::shouldReceive('ping')
            ->andReturn('ERR');

        $service = new HealthCheckService;
        $result = $service->check();

        $this->assertSame('unhealthy', $result['status']);
        $this->assertSame('failed', $result['checks']['queue']['status']);
        $this->assertStringContainsString('No se pudo verificar la conexion', $result['checks']['queue']['message']);
    }

    public function test_check_database_queue_successful(): void
    {
        config(['queue.default' => 'database']);
        config(['database.default' => 'sqlite']); // in-memory DB is available
        config(['queue.connections.database.connection' => 'sqlite']);
        config(['queue.connections.database.table' => 'jobs']);

        // We need to make sure the 'jobs' table exists in sqlite memory or we mock DB
        // Actually, since we use sqlite in memory, let's just mock DB to avoid migrating
        DB::shouldReceive('connection')
            ->with('sqlite')
            ->andReturnSelf();

        // First connection call is for checkDatabase()
        DB::shouldReceive('select')
            ->with('SELECT 1')
            ->andReturn([true]);

        // Second connection call is for checkDatabaseQueueConnection()
        DB::shouldReceive('table')
            ->with('jobs')
            ->andReturnSelf();
        DB::shouldReceive('select')
            ->with('id')
            ->andReturnSelf();
        DB::shouldReceive('limit')
            ->with(1)
            ->andReturnSelf();
        DB::shouldReceive('get')
            ->andReturn(collect([]));

        $service = new HealthCheckService;
        $result = $service->check();

        $this->assertSame('healthy', $result['status']);
        $this->assertSame('ok', $result['checks']['queue']['status']);
        $this->assertSame('database', $result['checks']['queue']['driver']);
    }

    public function test_check_other_queue_driver_successful(): void
    {
        config(['queue.default' => 'sqs']);

        Queue::shouldReceive('connection')
            ->with('sqs')
            ->andReturnSelf();
        Queue::shouldReceive('size')
            ->andReturn(0);

        $service = new HealthCheckService;
        $result = $service->check();

        $this->assertSame('healthy', $result['status']);
        $this->assertSame('ok', $result['checks']['queue']['status']);
        $this->assertSame('sqs', $result['checks']['queue']['driver']);
    }
}
