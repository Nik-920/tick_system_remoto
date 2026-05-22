<?php

namespace Tests\Unit\Jobs;

use App\Jobs\WriteAiAuditLog;
use App\Models\Ticket;
use App\Services\Observability\TicketQrLogger;
use Tests\TestCase;

class WriteAiAuditLogTest extends TestCase
{
    public function test_job_logs_audit_event_with_operation_type_and_ticket_context(): void
    {
        $ticket = new Ticket([
            'id' => 'ticket-1',
            'location_id' => 'loc-1',
            'category_id' => 'cat-1',
        ]);

        $job = new WriteAiAuditLog(
            'Duplicate ticket detected.',
            ['matched_ticket_id' => 'ticket-2'],
            $ticket,
            'semantic_dedup_check',
            'corr-001'
        );

        $logger = new CapturingTicketQrLogger;
        $job->handle($logger);

        $this->assertSame('ticket.ai.semantic.dedup.check', $logger->eventName);
        $this->assertSame('corr-001', $logger->context['correlation_id'] ?? null);
        $this->assertSame('Duplicate ticket detected.', $logger->context['audit_message'] ?? null);
        $this->assertSame('ticket-1', $logger->context['ticket_id'] ?? null);
        $this->assertSame('loc-1', $logger->context['location_id'] ?? null);
        $this->assertSame('cat-1', $logger->context['category_id'] ?? null);
        $this->assertSame('semantic_dedup_check', $logger->context['operation_type'] ?? null);
    }

    public function test_job_logs_default_event_when_operation_type_empty(): void
    {
        $job = new WriteAiAuditLog('Audit message');

        $logger = new CapturingTicketQrLogger;
        $job->handle($logger);

        $this->assertSame('ticket.ai.audit', $logger->eventName);
        $this->assertSame('Audit message', $logger->context['audit_message'] ?? null);
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
    public function info(string $eventName, array $context = []): void
    {
        $this->eventName = $eventName;
        $this->context = $context;
    }
}
