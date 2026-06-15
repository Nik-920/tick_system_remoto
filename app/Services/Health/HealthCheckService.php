<?php

namespace App\Services\Health;

use App\Exceptions\RedisHealthException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Throwable;

class HealthCheckService
{
    /**
     * @return array{
     *     status: string,
     *     timestamp: string,
     *     checks: array{
     *         database: array{status: string, connection: string, latency_ms: int, message?: string},
     *         queue: array{status: string, driver: string, latency_ms: int, message?: string},
     *         redis: array{status: string, latency_ms: int, required: bool, message?: string}
     *     }
     * }
     */
    public function check(): array
    {
        $database = $this->checkDatabase();
        $queue = $this->checkQueue();
        $redis = $this->checkRedis();

        $redisRequired = (bool) config('app.redis_health_required', false);
        $redisHealthy = $redis['status'] === 'ok' || ! $redisRequired;

        return [
            'status' => $database['status'] === 'ok' && $queue['status'] === 'ok' && $redisHealthy
                ? 'healthy'
                : 'unhealthy',
            'timestamp' => now()->toIso8601String(),
            'checks' => [
                'database' => $database,
                'queue' => $queue,
                'redis' => $redis,
            ],
        ];
    }

    /**
     * @return array{status: string, connection: string, latency_ms: int, message?: string}
     */
    private function checkDatabase(): array
    {
        $connectionName = (string) config('database.default');
        $startedAt = microtime(true);

        try {
            DB::connection($connectionName)->select('SELECT 1');

            return [
                'status' => 'ok',
                'connection' => $connectionName,
                'latency_ms' => $this->elapsedMilliseconds($startedAt),
            ];
        } catch (Throwable $exception) {
            Log::warning('health.check.database.failed', [
                'connection' => $connectionName,
                'error' => $exception->getMessage(),
            ]);

            return [
                'status' => 'failed',
                'connection' => $connectionName,
                'latency_ms' => $this->elapsedMilliseconds($startedAt),
                'message' => 'No se pudo verificar la conexion de base de datos.',
            ];
        }
    }

    /**
     * @return array{status: string, driver: string, latency_ms: int, message?: string}
     */
    private function checkQueue(): array
    {
        $driver = (string) config('queue.default');
        $startedAt = microtime(true);

        try {
            if ($driver === 'redis') {
                $this->checkRedisQueueConnection();
            } elseif ($driver === 'database') {
                $this->checkDatabaseQueueConnection();
            } elseif ($driver === 'sync' || $driver === 'null') {
                return [
                    'status' => 'ok',
                    'driver' => $driver,
                    'latency_ms' => $this->elapsedMilliseconds($startedAt),
                    'message' => 'El driver no requiere conectividad externa.',
                ];
            } else {
                Queue::connection($driver)->size();
            }

            return [
                'status' => 'ok',
                'driver' => $driver,
                'latency_ms' => $this->elapsedMilliseconds($startedAt),
            ];
        } catch (Throwable $exception) {
            Log::warning('health.check.queue.failed', [
                'driver' => $driver,
                'error' => $exception->getMessage(),
            ]);

            return [
                'status' => 'failed',
                'driver' => $driver,
                'latency_ms' => $this->elapsedMilliseconds($startedAt),
                'message' => 'No se pudo verificar la conexion de la cola.',
            ];
        }
    }

    /**
     * @return array{status: string, latency_ms: int, required: bool, message?: string}
     */
    private function checkRedis(): array
    {
        $startedAt = microtime(true);
        $required = (bool) config('app.redis_health_required', false);

        try {
            $result = Redis::connection('default')->ping();

            if (! $this->isRedisPingSuccessful($result)) {
                throw new RedisHealthException('Redis ping did not return a healthy response.');
            }

            return [
                'status' => 'ok',
                'latency_ms' => $this->elapsedMilliseconds($startedAt),
                'required' => $required,
            ];
        } catch (Throwable $exception) {
            Log::warning('health.check.redis.failed', [
                'error' => $exception->getMessage(),
                'required' => $required,
            ]);

            return [
                'status' => 'failed',
                'latency_ms' => $this->elapsedMilliseconds($startedAt),
                'required' => $required,
                'message' => 'No se pudo verificar la conexion Redis.',
            ];
        }
    }

    private function checkRedisQueueConnection(): void
    {
        $connectionName = (string) config('queue.connections.redis.connection', 'default');
        $result = Redis::connection($connectionName)->ping();

        if (! $this->isRedisPingSuccessful($result)) {
            throw new \RuntimeException('Redis ping did not return a healthy response.');
        }

        Queue::connection('redis')->size();
    }

    private function checkDatabaseQueueConnection(): void
    {
        $connectionName = config('queue.connections.database.connection') ?: config('database.default');
        $table = (string) config('queue.connections.database.table', 'jobs');

        DB::connection((string) $connectionName)
            ->table($table)
            ->select('id')
            ->limit(1)
            ->get();
    }

    private function isRedisPingSuccessful(mixed $result): bool
    {
        if (is_bool($result)) {
            return $result;
        }

        if (is_string($result)) {
            $normalized = ltrim(trim($result), '+');

            return strcasecmp($normalized, 'PONG') === 0;
        }

        return $result !== null;
    }

    private function elapsedMilliseconds(float $startedAt): int
    {
        return (int) round((microtime(true) - $startedAt) * 1000);
    }
}
