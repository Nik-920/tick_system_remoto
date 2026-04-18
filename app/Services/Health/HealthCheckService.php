<?php

namespace App\Services\Health;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use RuntimeException;
use Throwable;

class HealthCheckService
{
    /**
     * @return array{
     *     status: string,
     *     timestamp: string,
     *     checks: array{
     *         database: array{status: string, connection: string, latency_ms: int, message?: string},
     *         queue: array{status: string, driver: string, latency_ms: int, message?: string}
     *     }
     * }
     */
    public function check(): array
    {
        $database = $this->checkDatabase();
        $queue = $this->checkQueue();

        return [
            'status' => $database['status'] === 'ok' && $queue['status'] === 'ok' ? 'healthy' : 'unhealthy',
            'timestamp' => now()->toIso8601String(),
            'checks' => [
                'database' => $database,
                'queue' => $queue,
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

    private function checkRedisQueueConnection(): void
    {
        $connectionName = (string) config('queue.connections.redis.connection', 'default');
        $result = Redis::connection($connectionName)->ping();

        if (! $this->isRedisPingSuccessful($result)) {
            throw new RuntimeException('Redis ping did not return a healthy response.');
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
