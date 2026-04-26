<?php

namespace Tests\Unit\Listeners;

use App\Listeners\ReportFailedQueueJob;
use App\Services\Observability\TicketQrLogger;
use Illuminate\Queue\Events\JobFailed;
use RuntimeException;
use Tests\TestCase;

class ReportFailedQueueJobTest extends TestCase
{
    public function test_listener_logs_failed_job_with_correlation_id_from_payload(): void
    {
        config(['sentry.dsn' => null]);

        $serializedCommand = serialize(new FakeQueueCommand('corr-queue-001'));
        $job = new FakeQueueJob([
            'uuid' => 'job-uuid-001',
            'data' => [
                'command' => $serializedCommand,
            ],
        ]);

        $logger = new CapturingTicketQrLogger();
        $listener = new ReportFailedQueueJob($logger);

        $listener->handle(new JobFailed('database', $job, new RuntimeException('Queue exploded')));

        $this->assertSame('queue.job.failed', $logger->eventName);
        $this->assertSame('queue_job_failure', $logger->context['operation_type'] ?? null);
        $this->assertSame('corr-queue-001', $logger->context['correlation_id'] ?? null);
        $this->assertSame('database', $logger->context['connection_name'] ?? null);
        $this->assertSame('default', $logger->context['queue'] ?? null);
    }
}

class CapturingTicketQrLogger extends TicketQrLogger
{
    public string $eventName = '';

    /** @var array<string, mixed> */
    public array $context = [];

    /**
     * @param  array<string, mixed>  $context
     */
    public function error(string $eventName, array $context = []): void
    {
        $this->eventName = $eventName;
        $this->context = $context;
    }
}

class FakeQueueCommand
{
    public function __construct(public string $correlationId)
    {
    }
}

class FakeQueueJob
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(private array $payload)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return $this->payload;
    }

    public function getQueue(): string
    {
        return 'default';
    }

    public function resolveName(): string
    {
        return 'App\\Jobs\\FakeJob';
    }

    public function attempts(): int
    {
        return 3;
    }
}
