<?php

namespace Tests\Feature\Ai;

use App\Jobs\LogAiDecision;
use App\Models\Category;
use App\Models\Location;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LogAiDecisionJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_log_ai_decision_persists_correlation_id_in_ticket_ai_logs(): void
    {
        config(['ai.enabled' => true]);

        $reporter = User::factory()->create();

        $location = Location::create([
            'name' => 'Aula Correlacion',
            'building' => 'Edificio C',
            'floor' => '2',
            'room_code' => 'C-201',
            'qr_token' => 'qr-c-201-token',
            'is_active' => true,
        ]);

        $category = Category::create([
            'name' => 'Electricidad',
            'icon' => 'bolt',
            'description' => 'Incidencias electricas',
        ]);

        $ticket = Ticket::create([
            'title' => 'Luz intermitente',
            'description' => 'Ticket para probar persistencia de correlation_id en auditoria IA.',
            'reporter_id' => $reporter->id,
            'location_id' => $location->id,
            'category_id' => $category->id,
            'state' => 'open',
            'priority' => 'medium',
        ]);

        $correlationId = 'corr-ai-log-001';

        $job = new LogAiDecision(
            $ticket,
            'semantic_dedup_check',
            ['ticket_id' => $ticket->id],
            ['is_duplicate' => true],
            0.91,
            'flagged_duplicate',
            $correlationId,
        );

        $job->handle();

        $this->assertDatabaseHas('ticket_ai_logs', [
            'ticket_id' => $ticket->id,
            'operation_type' => 'semantic_dedup_check',
            'correlation_id' => $correlationId,
        ]);
    }
}
