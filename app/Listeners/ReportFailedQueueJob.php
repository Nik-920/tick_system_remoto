<?php

namespace App\Listeners;

use App\Services\Observability\TicketQrLogger;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Str;
use Sentry\State\Scope;

class ReportFailedQueueJob
{
    public function __construct(private TicketQrLogger $logger) {}

    public function handle(JobFailed $event): void
    {
        $payload = $event->job->payload();

        $context = array_filter([
            'domain' => 'queue',
            'operation_type' => 'queue_job_failure',
            'connection_name' => $event->connectionName,
            'queue' => $event->job->getQueue(),
            'job_name' => $event->job->resolveName(),
            'job_uuid' => $this->stringOrNull($payload['uuid'] ?? null),
            'attempts' => $event->job->attempts(),
            'correlation_id' => $this->extractCorrelationId($payload),
            'exception_class' => $event->exception::class,
            'error_message' => Str::limit($event->exception->getMessage(), 500, ''),
        ], static fn (mixed $value): bool => $value !== null && $value !== '');

        $this->logger->error('queue.job.failed', $context);

        \Sentry\withScope(function (Scope $scope) use ($context): void {
            $scope->setTag('domain', 'queue');

            $connection = (string) ($context['connection_name'] ?? 'unknown');
            $scope->setTag('queue_connection', $connection);

            $queue = $context['queue'] ?? null;
            if (is_string($queue) && $queue !== '') {
                $scope->setTag('queue', $queue);
            }

            $correlationId = $context['correlation_id'] ?? null;
            if (is_string($correlationId) && $correlationId !== '') {
                $scope->setTag('correlation_id', $correlationId);
            }

            $scope->setContext('queue_job', $context);
        });

        \Sentry\captureException($event->exception);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function extractCorrelationId(array $payload): ?string
    {
        foreach (['correlation_id', 'correlationId'] as $key) {
            $value = $payload[$key] ?? null;
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        $data = $payload['data'] ?? null;
        if (! is_array($data)) {
            return null;
        }

        foreach (['correlation_id', 'correlationId'] as $key) {
            $value = $data[$key] ?? null;
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        $serializedCommand = $data['command'] ?? null;
        if (! is_string($serializedCommand) || $serializedCommand === '') {
            return null;
        }

        $matched = [];
        $pattern = '/"correlation(?:Id|_id)";s:\\d+:"([^"]+)"/';
        if (preg_match($pattern, $serializedCommand, $matched) !== 1) {
            return null;
        }

        $candidate = trim((string) ($matched[1] ?? ''));

        return $candidate === '' ? null : $candidate;
    }

    private function stringOrNull(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }
}
