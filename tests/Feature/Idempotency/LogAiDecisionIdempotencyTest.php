<?php

namespace Tests\Feature\Idempotency;

use App\Jobs\LogAiDecision;
use App\Models\Category;
use App\Models\Location;
use App\Models\Ticket;
use App\Models\TicketAiLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fase 5.3 — un reintento del job LogAiDecision no debe crear un segundo
 * registro de auditoría para la misma decisión (ticket_id, operation_type,
 * correlation_id). Garantizado por firstOrCreate en LogAiDecision::handle().
 */
class LogAiDecisionIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_retry_of_log_ai_decision_does_not_create_second_ticket_ai_log(): void
    {
        config(['ai.enabled' => true]);

        $ticket = $this->makeTicket();
        $correlationId = 'corr-idem-001';

        $job = new LogAiDecision(
            $ticket,
            'semantic_dedup_check',
            ['ticket_id' => $ticket->id],
            ['is_duplicate' => true],
            0.91,
            'flagged_duplicate',
            $correlationId,
        );

        // Ejecución original + reintento (mismo job, mismos argumentos)
        $job->handle();
        $job->handle();

        $count = TicketAiLog::query()
            ->where('ticket_id', $ticket->id)
            ->where('operation_type', 'semantic_dedup_check')
            ->where('correlation_id', $correlationId)
            ->count();

        $this->assertSame(1, $count, 'El reintento NO debe crear un segundo TicketAiLog.');
    }

    public function test_distinct_correlation_ids_create_separate_logs(): void
    {
        config(['ai.enabled' => true]);

        $ticket = $this->makeTicket();

        (new LogAiDecision($ticket, 'semantic_dedup_check', [], [], null, null, 'corr-A'))->handle();
        (new LogAiDecision($ticket, 'semantic_dedup_check', [], [], null, null, 'corr-B'))->handle();

        $count = TicketAiLog::query()
            ->where('ticket_id', $ticket->id)
            ->where('operation_type', 'semantic_dedup_check')
            ->count();

        $this->assertSame(2, $count, 'Decisiones con correlation_id distinto son registros distintos.');
    }

    private function makeTicket(): Ticket
    {
        $reporter = User::factory()->create();

        $location = Location::create([
            'name' => 'Aula Idempotencia',
            'building' => 'Edificio I',
            'floor' => '1',
            'room_code' => 'I-101',
            'qr_token' => 'qr-i-101-token',
            'is_active' => true,
        ]);

        $category = Category::create([
            'name' => 'Electricidad',
            'icon' => 'bolt',
            'description' => 'Incidencias electricas',
        ]);

        return Ticket::create([
            'title' => 'Luz intermitente',
            'description' => 'Ticket para probar idempotencia de auditoria IA.',
            'reporter_id' => $reporter->id,
            'location_id' => $location->id,
            'category_id' => $category->id,
            'state' => 'open',
            'priority' => 'medium',
        ]);
    }
}
