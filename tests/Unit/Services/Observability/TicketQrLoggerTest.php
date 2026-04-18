<?php

namespace Tests\Unit\Services\Observability;

use App\Services\Observability\TicketQrLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class TicketQrLoggerTest extends TestCase
{
    public function test_logger_writes_required_fields_to_ticket_qr_channel(): void
    {
        $request = Request::create('/api/tickets', 'POST');
        $request->headers->set('X-Request-Id', 'req-123');
        $this->app->instance('request', $request);

        $logger = new TicketQrLogger();

        Log::shouldReceive('channel')->once()->with('ticket_qr')->andReturnSelf();
        Log::shouldReceive('log')->once()->withArgs(function (string $level, string $message, array $payload): bool {
            return $level === 'info'
                && $message === 'ticket.creation.succeeded'
                && ($payload['event_name'] ?? null) === 'ticket.creation.succeeded'
                && ($payload['domain'] ?? null) === 'ticket'
                && ($payload['level'] ?? null) === 'info'
                && ($payload['request_id'] ?? null) === 'req-123'
                && ($payload['actor_id'] ?? null) === 'user-1'
                && ($payload['ticket_id'] ?? null) === 'ticket-1'
                && ($payload['location_id'] ?? null) === 'location-1'
                && is_string($payload['correlation_id'] ?? null)
                && is_string($payload['occurred_at'] ?? null)
                && is_array($payload['context'] ?? null);
        });

        $logger->info('ticket.creation.succeeded', [
            'actor_id' => 'user-1',
            'ticket_id' => 'ticket-1',
            'location_id' => 'location-1',
            'category_id' => 'category-1',
        ]);
    }

    public function test_logger_hashes_and_redacts_sensitive_fields(): void
    {
        $logger = new TicketQrLogger();

        Log::shouldReceive('channel')->once()->with('ticket_qr')->andReturnSelf();
        Log::shouldReceive('log')->once()->withArgs(function (string $level, string $message, array $payload): bool {
            $context = $payload['context'] ?? [];

            return $level === 'warning'
                && $message === 'qr.scan.invalid_token'
                && ($context['description'] ?? null) === '[REDACTED]'
                && ($context['comment'] ?? null) === '[REDACTED]'
                && ($context['title'] ?? null) === '[REDACTED]'
                && ($context['qr_token'] ?? null) === hash('sha256', 'token-secret')
                && ($context['email'] ?? null) === hash('sha256', 'student@example.com');
        });

        $logger->warning('qr.scan.invalid_token', [
            'description' => 'Descripcion sensible',
            'comment' => 'Comentario sensible',
            'title' => 'Titulo sensible',
            'qr_token' => 'token-secret',
            'email' => 'student@example.com',
        ]);
    }

    public function test_logger_prefers_correlation_id_from_request_header(): void
    {
        $request = Request::create('/health', 'GET');
        $request->headers->set('X-Correlation-Id', 'corr-001');
        $this->app->instance('request', $request);

        $logger = new TicketQrLogger();

        Log::shouldReceive('channel')->once()->with('ticket_qr')->andReturnSelf();
        Log::shouldReceive('log')->once()->withArgs(function (string $level, string $message, array $payload): bool {
            return $level === 'info'
                && $message === 'qr.generation.dispatched'
                && ($payload['correlation_id'] ?? null) === 'corr-001';
        });

        $logger->info('qr.generation.dispatched', [
            'location_id' => 'loc-1',
            'qr_job_id' => 'job-1',
        ]);
    }
}
