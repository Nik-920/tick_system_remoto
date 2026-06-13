<?php

namespace Tests\Unit\Dashboard;

use App\Models\Category;
use App\Models\Location;
use App\Models\LocationIncidentHistory;
use App\Models\StateHistory;
use App\Models\Ticket;
use App\Models\TicketEmbedding;
use App\Models\TicketMedia;
use App\Models\User;
use App\Queries\Dashboard\MaintenanceReportQuery;
use App\Support\Dashboard\DateRange;
use App\ViewModels\Dashboard\MaintenanceReport\AssignmentRow;
use App\ViewModels\Dashboard\MaintenanceReport\KpiRow;
use App\ViewModels\Dashboard\MaintenanceReportViewModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Unit coverage for the dedicated professional-report query layer:
 * universes, two clocks, deterministic operational order, evidence,
 * rejected/cancelled treatment, AI-duplicate signals and data quality.
 */
class MaintenanceReportQueryTest extends TestCase
{
    use RefreshDatabase;

    private User $technician;

    private Location $location;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-06-15 12:00:00'));

        $this->technician = User::factory()->create();
        $this->location = $this->createLocation('Laboratorio 1', 'L-101');
        $this->category = $this->createCategory('Hardware');
    }

    // ── Universos y separación de métricas ───────────────────────────────────

    public function test_personal_metrics_only_use_tickets_assigned_to_the_technician(): void
    {
        $other = User::factory()->create();

        $this->createAssignedTicket($this->technician, ['state' => 'open']);
        $this->createAssignedTicket($other, ['state' => 'open', 'title' => 'Ticket ajeno']);

        $vm = $this->buildReport();

        $this->assertCount(1, $vm->currentAssignments);
        $this->assertSame('1', $this->kpi($vm, 'assigned_active')->value);
    }

    public function test_global_queue_is_context_and_never_feeds_personal_kpis(): void
    {
        $this->createAssignedTicket($this->technician, ['state' => 'open']);

        // Open, unassigned, unlocked → claimable queue.
        $this->createTicket(['state' => 'open', 'title' => 'Cola global 1']);
        $this->createTicket(['state' => 'open', 'title' => 'Cola global 2']);

        $vm = $this->buildReport();

        $this->assertSame(2, $vm->globalQueueContext['count']);
        $this->assertSame('1', $this->kpi($vm, 'assigned_active')->value);
        $this->assertSame('2', $this->kpi($vm, 'global_queue')->value);
        $this->assertSame(KpiRow::CLOCK_SNAPSHOT, $this->kpi($vm, 'global_queue')->clock);
    }

    public function test_active_assignments_are_snapshot_and_include_tickets_created_before_the_period(): void
    {
        $old = $this->createAssignedTicket($this->technician, ['state' => 'in_progress']);
        $old->forceFill(['created_at' => Carbon::parse('2026-01-05 08:00:00')])->save();

        $vm = $this->buildReport();

        $this->assertCount(1, $vm->currentAssignments);
        $this->assertSame($old->id, $vm->currentAssignments[0]->id);
    }

    public function test_active_assignments_exclude_resolved_rejected_and_cancelled(): void
    {
        $this->createAssignedTicket($this->technician, ['state' => 'open']);
        $this->createAssignedTicket($this->technician, ['state' => 'resolved']);
        $this->createAssignedTicket($this->technician, ['state' => 'rejected']);
        $this->createAssignedTicket($this->technician, ['state' => 'cancelled']);

        $vm = $this->buildReport();

        $this->assertCount(1, $vm->currentAssignments);
        $this->assertSame('open', $vm->currentAssignments[0]->state);
    }

    public function test_scope_universe_counts_are_reported_with_their_clock(): void
    {
        $this->createAssignedTicket($this->technician, ['state' => 'open']);
        $resolved = $this->createAssignedTicket($this->technician, ['state' => 'resolved']);
        $resolved->forceFill(['resolved_at' => Carbon::parse('2026-06-10 10:00:00')])->save();
        $this->createTicket(['state' => 'open']); // global queue

        $vm = $this->buildReport();

        $byCode = [];
        foreach ($vm->scope as $row) {
            $byCode[$row->code] = $row;
        }

        $this->assertSame(2, $byCode['A']->count); // assigned all-time
        $this->assertSame(1, $byCode['B']->count); // active
        $this->assertSame(1, $byCode['C']->count); // global queue
        $this->assertSame(1, $byCode['D']->count); // resolved in period
        $this->assertSame('snapshot', $byCode['B']->clock);
        $this->assertSame('periodo', $byCode['D']->clock);
    }

    // ── Asignaciones: ubicación, evidencias, última transición ───────────────

    public function test_assignment_rows_expose_full_location_evidence_and_last_transition(): void
    {
        $ticket = $this->createAssignedTicket($this->technician, ['state' => 'in_progress']);

        TicketMedia::create([
            'ticket_id' => $ticket->id,
            'file_url' => 'https://example.test/evidencia.jpg',
            'file_type' => 'image',
            'uploaded_by' => $this->technician->id,
        ]);

        $this->addTransition($ticket, 'open', 'in_progress', Carbon::parse('2026-06-12 09:00:00'));
        $this->addTransition($ticket, 'in_progress', 'open', Carbon::parse('2026-06-13 10:00:00'));

        $vm = $this->buildReport();
        $row = $vm->currentAssignments[0];

        $this->assertSame('Laboratorio 1', $row->locationName);
        $this->assertSame('Edificio Z', $row->building);
        $this->assertSame('1', $row->floor);
        $this->assertSame('L-101', $row->roomCode);
        $this->assertSame('Hardware', $row->categoryName);
        $this->assertTrue($row->hasEvidence);
        $this->assertSame(1, $row->evidenceCount);
        $this->assertNotNull($row->lastTransition);
        $this->assertSame('open', $row->lastTransition['toState']);
        $this->assertSame('2026-06-13', $row->lastTransition['at']->format('Y-m-d'));
        $this->assertTrue($row->hasFirstResponse);
        $this->assertSame('2026-06-12', $row->firstResponseAt?->format('Y-m-d'));
        $this->assertSame('#TIC-'.substr($ticket->id, 0, 8), $row->displayId());
    }

    // ── Orden operativo determinista ─────────────────────────────────────────

    public function test_operational_order_is_deterministic_and_explainable(): void
    {
        $low = $this->createAssignedTicket($this->technician, ['state' => 'open', 'priority' => 'low', 'title' => 'Baja']);
        $low->forceFill(['created_at' => Carbon::parse('2026-06-01 08:00:00')])->save();

        $inProgressOld = $this->createAssignedTicket($this->technician, ['state' => 'in_progress', 'priority' => 'medium', 'title' => 'Progreso antiguo']);
        $inProgressOld->forceFill(['created_at' => Carbon::parse('2026-06-02 08:00:00')])->save();

        $openOldNoResponse = $this->createAssignedTicket($this->technician, ['state' => 'open', 'priority' => 'medium', 'title' => 'Abierto antiguo sin respuesta']);
        $openOldNoResponse->forceFill(['created_at' => Carbon::parse('2026-06-03 08:00:00')])->save();

        $openNewNoResponse = $this->createAssignedTicket($this->technician, ['state' => 'open', 'priority' => 'medium', 'title' => 'Abierto nuevo sin respuesta']);
        $openNewNoResponse->forceFill(['created_at' => Carbon::parse('2026-06-12 08:00:00')])->save();

        $openWithResponse = $this->createAssignedTicket($this->technician, ['state' => 'open', 'priority' => 'medium', 'title' => 'Abierto con respuesta previa']);
        $openWithResponse->forceFill(['created_at' => Carbon::parse('2026-06-01 07:00:00')])->save();
        $this->addTransition($openWithResponse, 'open', 'in_progress', Carbon::parse('2026-06-02 09:00:00'));
        $this->addTransition($openWithResponse, 'in_progress', 'open', Carbon::parse('2026-06-03 09:00:00'));

        $high = $this->createAssignedTicket($this->technician, ['state' => 'open', 'priority' => 'high', 'title' => 'Alta']);
        $high->forceFill(['created_at' => Carbon::parse('2026-06-14 08:00:00')])->save();

        $critical = $this->createAssignedTicket($this->technician, ['state' => 'in_progress', 'priority' => 'critical', 'title' => 'Crítico']);
        $critical->forceFill(['created_at' => Carbon::parse('2026-06-14 10:00:00')])->save();

        $vm = $this->buildReport();
        $titles = array_map(static fn (AssignmentRow $row): string => $row->title, $vm->currentAssignments);

        $this->assertSame([
            'Crítico',
            'Alta',
            'Abierto antiguo sin respuesta',
            'Abierto nuevo sin respuesta',
            'Abierto con respuesta previa',
            'Progreso antiguo',
            'Baja',
        ], $titles);
    }

    public function test_stale_open_tickets_are_listed_not_only_counted(): void
    {
        $stale = $this->createAssignedTicket($this->technician, ['state' => 'open', 'title' => 'Atrasado']);
        $stale->forceFill(['created_at' => Carbon::parse('2026-06-05 08:00:00')])->save();

        $fresh = $this->createAssignedTicket($this->technician, ['state' => 'open', 'title' => 'Reciente']);
        $fresh->forceFill(['created_at' => Carbon::parse('2026-06-14 08:00:00')])->save();

        $vm = $this->buildReport();

        $staleTitles = array_map(static fn (AssignmentRow $row): string => $row->title, $vm->criticalAndDelayed['staleOpen']);
        $this->assertSame(['Atrasado'], $staleTitles);
        $this->assertSame('1', $this->kpi($vm, 'stale_open')->value);
    }

    // ── Resueltos, rechazados y cancelados (reloj periodo) ───────────────────

    public function test_resolved_tickets_count_by_resolved_at_within_the_period_only(): void
    {
        $inside = $this->createAssignedTicket($this->technician, ['state' => 'resolved', 'title' => 'Dentro']);
        $inside->forceFill([
            'created_at' => Carbon::parse('2026-06-09 10:00:00'),
            'resolved_at' => Carbon::parse('2026-06-10 10:00:00'),
        ])->save();

        $outside = $this->createAssignedTicket($this->technician, ['state' => 'resolved', 'title' => 'Fuera']);
        $outside->forceFill([
            'created_at' => Carbon::parse('2026-05-01 10:00:00'),
            'resolved_at' => Carbon::parse('2026-05-02 10:00:00'),
        ])->save();

        $vm = $this->buildReport();

        $this->assertCount(1, $vm->resolvedTickets);
        $this->assertSame('Dentro', $vm->resolvedTickets[0]->title);
        $this->assertSame(24.0, $vm->resolvedTickets[0]->durationHours);
        $this->assertSame('1', $this->kpi($vm, 'resolved_period')->value);
    }

    public function test_rejected_and_cancelled_come_from_state_history_transitions_within_period(): void
    {
        $rejected = $this->createAssignedTicket($this->technician, ['state' => 'rejected', 'title' => 'Rechazado periodo']);
        $this->addTransition($rejected, 'open', 'rejected', Carbon::parse('2026-06-08 09:00:00'));

        $cancelled = $this->createAssignedTicket($this->technician, ['state' => 'cancelled', 'title' => 'Cancelado periodo']);
        $this->addTransition($cancelled, 'open', 'cancelled', Carbon::parse('2026-06-09 09:00:00'));

        $rejectedOutside = $this->createAssignedTicket($this->technician, ['state' => 'rejected', 'title' => 'Rechazado fuera']);
        $this->addTransition($rejectedOutside, 'open', 'rejected', Carbon::parse('2026-04-01 09:00:00'));

        $vm = $this->buildReport();

        $this->assertCount(1, $vm->rejectedTickets);
        $this->assertSame('Rechazado periodo', $vm->rejectedTickets[0]->title);
        $this->assertCount(1, $vm->cancelledTickets);
        $this->assertSame('Cancelado periodo', $vm->cancelledTickets[0]->title);
        $this->assertSame('1', $this->kpi($vm, 'rejected_period')->value);
        $this->assertSame('1', $this->kpi($vm, 'cancelled_period')->value);
        // Cerrados = resueltos + rechazados; excluye cancelados.
        $this->assertSame('1', $this->kpi($vm, 'closed_period')->value);
    }

    public function test_cancelled_does_not_enter_the_close_rate(): void
    {
        $resolved = $this->createAssignedTicket($this->technician, ['state' => 'resolved']);
        $resolved->forceFill([
            'created_at' => Carbon::parse('2026-06-09 10:00:00'),
            'resolved_at' => Carbon::parse('2026-06-10 10:00:00'),
        ])->save();

        $this->createAssignedTicket($this->technician, ['state' => 'open']);

        $cancelled = $this->createAssignedTicket($this->technician, ['state' => 'cancelled']);
        $this->addTransition($cancelled, 'open', 'cancelled', Carbon::parse('2026-06-09 09:00:00'));

        $vm = $this->buildReport();

        // 1 resuelto / (1 resuelto + 1 activo) = 50 %. El cancelado no aparece.
        $this->assertSame('50 %', $this->kpi($vm, 'close_rate')->value);
    }

    // ── Primera respuesta y muestra baja ─────────────────────────────────────

    public function test_first_response_uses_the_first_in_progress_transition(): void
    {
        $ticket = $this->createAssignedTicket($this->technician, ['state' => 'in_progress']);
        $ticket->forceFill(['created_at' => Carbon::parse('2026-06-10 00:00:00')])->save();
        $this->addTransition($ticket, 'open', 'in_progress', Carbon::parse('2026-06-10 06:00:00'));
        // Una segunda transición no debe cambiar la primera respuesta.
        $this->addTransition($ticket, 'open', 'in_progress', Carbon::parse('2026-06-11 06:00:00'));

        $vm = $this->buildReport();

        $this->assertSame(6.0, $vm->timeMetrics['avgFirstResponseHours']);
        $this->assertSame(1, $vm->timeMetrics['firstResponseSample']);
        $this->assertTrue($vm->timeMetrics['firstResponseLowSample']);
    }

    public function test_time_metrics_with_small_samples_are_flagged_as_low_sample(): void
    {
        $resolved = $this->createAssignedTicket($this->technician, ['state' => 'resolved']);
        $resolved->forceFill([
            'created_at' => Carbon::parse('2026-06-09 10:00:00'),
            'resolved_at' => Carbon::parse('2026-06-11 10:00:00'),
        ])->save();

        $vm = $this->buildReport();

        $this->assertSame(48.0, $vm->timeMetrics['avgResolutionHours']);
        $this->assertSame(48.0, $vm->timeMetrics['medianResolutionHours']);
        $this->assertSame(1, $vm->timeMetrics['resolutionSample']);
        $this->assertTrue($vm->timeMetrics['resolutionLowSample']);
        $this->assertTrue($this->kpi($vm, 'avg_resolution')->lowSample);
    }

    // ── Evidencias ───────────────────────────────────────────────────────────

    public function test_evidence_summary_lists_active_tickets_without_evidence(): void
    {
        $with = $this->createAssignedTicket($this->technician, ['state' => 'open', 'title' => 'Con evidencia']);
        TicketMedia::create([
            'ticket_id' => $with->id,
            'file_url' => 'https://example.test/foto.jpg',
            'file_type' => 'image',
            'uploaded_by' => $this->technician->id,
        ]);

        $this->createAssignedTicket($this->technician, ['state' => 'open', 'title' => 'Sin evidencia']);

        $vm = $this->buildReport();

        $this->assertSame(1, $vm->evidenceSummary['withEvidence']);
        $this->assertSame(1, $vm->evidenceSummary['withoutEvidence']);
        $this->assertSame(50.0, $vm->evidenceSummary['coveragePct']);
        $this->assertSame('Sin evidencia', $vm->evidenceSummary['missing'][0]['title']);
    }

    // ── Duplicados IA (universo I, señal operativa) ──────────────────────────

    public function test_duplicate_alerts_expose_strategy_explanation_without_raw_json(): void
    {
        $matched = $this->createTicket(['title' => 'Proyector dañado original', 'state' => 'open']);
        $ticket = $this->createAssignedTicket($this->technician, ['state' => 'open', 'title' => 'Proyector dañado']);

        $this->createEmbedding($ticket, $matched, [
            'similarity_score' => 0.95,
            'strategy_score' => 75,
            'strategy_results' => $this->sampleStrategyResults(),
        ]);

        $vm = $this->buildReport();

        $this->assertCount(1, $vm->duplicateAlerts);
        $alert = $vm->duplicateAlerts[0];

        $this->assertSame(0.95, $alert->similarity);
        $this->assertSame(75, $alert->strategyScore);
        $this->assertSame('Proyector dañado original', $alert->matchedTicketTitle);
        $this->assertSame('Pendiente de revisión', $alert->reviewStatusLabel);
        $this->assertFalse($alert->isFallback);

        $labels = array_column($alert->topReasons, 'label');
        $this->assertContains('Alta similitud semántica', $labels);
        $this->assertContains('Misma ubicación', $labels);
        $this->assertContains('Misma categoría', $labels);

        // Nunca JSON crudo: las razones son texto humano, no claves técnicas.
        foreach ($alert->topReasons as $reason) {
            $this->assertStringNotContainsString('blocksDuplicate', $reason['label'].$reason['detail']);
            $this->assertStringNotContainsString('{', $reason['label'].$reason['detail']);
        }

        // La asignación correspondiente también lleva la advertencia.
        $row = $vm->currentAssignments[0];
        $this->assertTrue($row->hasDuplicateWarning);
        $this->assertSame('Revisar posible duplicado antes de continuar', $row->recommendedAction);
    }

    public function test_legacy_duplicate_without_strategy_results_uses_fallback(): void
    {
        $matched = $this->createTicket(['title' => 'Antiguo similar', 'state' => 'open']);
        $ticket = $this->createAssignedTicket($this->technician, ['state' => 'open', 'title' => 'Antiguo duplicado']);

        $this->createEmbedding($ticket, $matched, [
            'similarity_score' => 0.91,
            'strategy_results' => null,
        ]);

        $vm = $this->buildReport();
        $alert = $vm->duplicateAlerts[0];

        $this->assertTrue($alert->isFallback);
        $this->assertNull($alert->strategyScore);
        $this->assertSame([], $alert->topReasons);
        $this->assertStringContainsString('No hay un desglose detallado', $alert->summary);
        // El detalle IA vive en aiSummary (sección de alertas), no en los KPIs.
        $this->assertSame(1, $vm->aiSummary['legacyWithoutStrategy']);
    }

    public function test_strategy_recurrence_signal_is_differentiated_from_immediate_duplicate(): void
    {
        $matched = $this->createTicket(['title' => 'Incidente previo', 'state' => 'resolved']);
        $ticket = $this->createAssignedTicket($this->technician, ['state' => 'open', 'title' => 'Posible recurrencia']);

        $this->createEmbedding($ticket, $matched, [
            'similarity_score' => 0.88,
            'strategy_score' => 40,
            'strategy_suggests_recurrence' => true,
            'strategy_results' => [
                [
                    'strategy' => 'recurrence_guard',
                    'points' => 0,
                    'reason' => 'Old candidate, likely recurrence',
                    'metadata' => [],
                    'blocksDuplicate' => false,
                    'suggestsRecurrence' => true,
                ],
                [
                    'strategy' => 'embedding_similarity',
                    'points' => 40,
                    'reason' => 'High similarity',
                    'metadata' => ['similarity' => 0.88],
                    'blocksDuplicate' => false,
                    'suggestsRecurrence' => false,
                ],
            ],
        ]);

        $vm = $this->buildReport();
        $alert = $vm->duplicateAlerts[0];

        $this->assertTrue($alert->suggestsRecurrence);
        $this->assertSame(1, $vm->aiSummary['suggestsRecurrence']);

        $types = array_map(static fn ($risk): string => $risk->type, $vm->risks);
        $this->assertContains('possible_recurrence_detected', $types);
    }

    public function test_dismissed_duplicates_are_excluded_from_the_report(): void
    {
        $matched = $this->createTicket(['title' => 'Similar descartado', 'state' => 'open']);
        $ticket = $this->createAssignedTicket($this->technician, ['state' => 'open']);

        $this->createEmbedding($ticket, $matched, [
            'similarity_score' => 0.95,
            'review_status' => TicketEmbedding::REVIEW_DISMISSED,
        ]);

        $vm = $this->buildReport();

        $this->assertCount(0, $vm->duplicateAlerts);
        $this->assertSame('0', $this->kpi($vm, 'ai_duplicates_active')->value);
        $this->assertFalse($vm->currentAssignments[0]->hasDuplicateWarning);
    }

    public function test_duplicates_never_affect_the_close_rate(): void
    {
        $resolved = $this->createAssignedTicket($this->technician, ['state' => 'resolved']);
        $resolved->forceFill([
            'created_at' => Carbon::parse('2026-06-09 10:00:00'),
            'resolved_at' => Carbon::parse('2026-06-10 10:00:00'),
        ])->save();

        $matched = $this->createTicket(['title' => 'Otro', 'state' => 'open']);
        $active = $this->createAssignedTicket($this->technician, ['state' => 'open']);
        $this->createEmbedding($active, $matched, ['similarity_score' => 0.99]);

        $vm = $this->buildReport();

        // Misma fórmula con o sin duplicados: 1 / (1 + 1) = 50 %.
        $this->assertSame('50 %', $this->kpi($vm, 'close_rate')->value);
        $this->assertSame('1', $this->kpi($vm, 'ai_duplicates_active')->value);
    }

    public function test_missing_matched_ticket_does_not_break_the_report(): void
    {
        $matched = $this->createTicket(['title' => 'Se borrará', 'state' => 'open']);
        $ticket = $this->createAssignedTicket($this->technician, ['state' => 'open']);
        $this->createEmbedding($ticket, $matched, ['similarity_score' => 0.93]);

        // La FK es nullOnDelete: al borrar el similar, matched_ticket_id queda
        // null. El informe no debe romperse y debe reportar la inconsistencia.
        $matched->delete();

        $vm = $this->buildReport();

        $this->assertCount(0, $vm->duplicateAlerts);
        $this->assertSame('0', $this->kpi($vm, 'ai_duplicates_active')->value);

        $checks = array_column($vm->dataQuality, 'check');
        $this->assertContains('duplicado_sin_match', $checks);
    }

    public function test_corrupt_strategy_results_fall_back_gracefully(): void
    {
        $matched = $this->createTicket(['title' => 'Similar', 'state' => 'open']);
        $ticket = $this->createAssignedTicket($this->technician, ['state' => 'open']);

        $this->createEmbedding($ticket, $matched, [
            'similarity_score' => 0.92,
            'strategy_results' => ['garbage', 123, ['no_strategy_key' => true]],
        ]);

        $vm = $this->buildReport();
        $alert = $vm->duplicateAlerts[0];

        $this->assertTrue($alert->isFallback);
        $this->assertSame([], $alert->topReasons);

        $checks = array_column($vm->dataQuality, 'check');
        $this->assertContains('strategy_results_corrupto', $checks);
    }

    // ── Recurrencias históricas, riesgos y recomendaciones ───────────────────

    public function test_recurrence_insights_come_from_location_incident_history(): void
    {
        $this->createAssignedTicket($this->technician, ['state' => 'open']);

        LocationIncidentHistory::create([
            'location_id' => $this->location->id,
            'category_id' => $this->category->id,
            'last_resolved_at' => Carbon::parse('2026-05-20 10:00:00'),
            'recurrence_count' => 4,
            'avg_resolution_time' => '2 días aprox.',
        ]);

        $vm = $this->buildReport();

        $this->assertCount(1, $vm->recurrenceInsights);
        $insight = $vm->recurrenceInsights[0];
        $this->assertSame('Laboratorio 1', $insight->locationName);
        $this->assertSame(4, $insight->recurrenceCount);
        $this->assertSame('2 días aprox.', $insight->avgResolutionLabel);
    }

    public function test_recurrence_insights_empty_when_no_history_matches(): void
    {
        $this->createAssignedTicket($this->technician, ['state' => 'open']);

        $vm = $this->buildReport();

        $this->assertSame([], $vm->recurrenceInsights);
    }

    public function test_risks_and_recommendations_cover_critical_and_stale_tickets(): void
    {
        $critical = $this->createAssignedTicket($this->technician, ['state' => 'open', 'priority' => 'critical', 'title' => 'Urgente']);
        $critical->forceFill(['created_at' => Carbon::parse('2026-06-14 08:00:00')])->save();

        $stale = $this->createAssignedTicket($this->technician, ['state' => 'open', 'title' => 'Viejo']);
        $stale->forceFill(['created_at' => Carbon::parse('2026-06-01 08:00:00')])->save();

        $vm = $this->buildReport();

        $types = array_map(static fn ($risk): string => $risk->type, $vm->risks);
        $this->assertContains('critical_active', $types);
        $this->assertContains('stale_open', $types);

        $joined = implode(' ', $vm->recommendations);
        $this->assertStringContainsString('críticos/alta', $joined);
        $this->assertStringContainsString('más de 3 días', $joined);

        $this->assertSame('Atención inmediata', $vm->currentAssignments[0]->recommendedAction);
    }

    public function test_data_quality_flags_in_progress_without_transition(): void
    {
        // En progreso sin transición registrada en state_history.
        $this->createAssignedTicket($this->technician, ['state' => 'in_progress']);

        $vm = $this->buildReport();

        $checks = array_column($vm->dataQuality, 'check');
        $this->assertContains('in_progress_sin_transicion', $checks);
    }

    public function test_report_handles_a_technician_with_no_data_at_all(): void
    {
        $vm = $this->buildReport();

        $this->assertSame([], $vm->currentAssignments);
        $this->assertSame([], $vm->resolvedTickets);
        $this->assertSame([], $vm->duplicateAlerts);
        $this->assertSame('0', $this->kpi($vm, 'assigned_active')->value);
        $this->assertSame('Sin datos', $this->kpi($vm, 'avg_resolution')->value);
        $this->assertNotEmpty($vm->executiveSummary['narrative']);
        $this->assertNotEmpty($vm->definitions);
        $this->assertSame(0, $vm->appendixTickets['totalRows']);
    }

    // ── Correcciones con poca data (tasa de cierre, anexo, breakdowns) ──────

    public function test_close_rate_shows_sin_datos_when_denominator_is_zero(): void
    {
        // Sin activos ni resueltos: la tasa no aplica y nunca muestra 0 %.
        $vm = $this->buildReport();

        $kpi = $this->kpi($vm, 'close_rate');
        $this->assertSame('Sin datos', $kpi->value);
        $this->assertStringNotContainsString('0 %', $kpi->value);
    }

    public function test_close_rate_still_shows_percentage_with_data(): void
    {
        $resolved = $this->createAssignedTicket($this->technician, ['state' => 'resolved']);
        $resolved->forceFill([
            'created_at' => Carbon::parse('2026-06-09 10:00:00'),
            'resolved_at' => Carbon::parse('2026-06-10 10:00:00'),
        ])->save();

        $vm = $this->buildReport();

        $this->assertSame('100 %', $this->kpi($vm, 'close_rate')->value);
    }

    public function test_appendix_includes_created_in_period_ticket_even_without_active_or_resolved(): void
    {
        // A=1 / G=1 pero B=0 y D=0: resuelto fuera del periodo, creado dentro.
        $ticket = $this->createAssignedTicket($this->technician, ['state' => 'resolved', 'title' => 'Creado en rango']);
        $ticket->forceFill([
            'created_at' => Carbon::parse('2026-06-05 10:00:00'),
            'resolved_at' => Carbon::parse('2026-06-20 10:00:00'), // fuera del rango 01–15
        ])->save();

        $vm = $this->buildReport();

        $this->assertSame([], $vm->currentAssignments);
        $this->assertSame([], $vm->resolvedTickets);
        $this->assertSame(1, $vm->appendixTickets['totalRows']);
        $this->assertSame('Creado en rango', $vm->appendixTickets['rows'][0]['title']);
        $this->assertStringContainsString('Creado en periodo', $vm->appendixTickets['rows'][0]['inclusionReason']);
    }

    public function test_appendix_includes_historical_assigned_tickets_with_reason(): void
    {
        // A=1 pero ni activo, ni creado en periodo, ni cerrado en periodo.
        $old = $this->createAssignedTicket($this->technician, ['state' => 'resolved', 'title' => 'Histórico']);
        $old->forceFill([
            'created_at' => Carbon::parse('2026-01-05 10:00:00'),
            'resolved_at' => Carbon::parse('2026-01-06 10:00:00'),
        ])->save();

        $vm = $this->buildReport();

        $this->assertSame(1, $vm->appendixTickets['totalRows']);
        $this->assertSame('Asignado histórico', $vm->appendixTickets['rows'][0]['inclusionReason']);
    }

    public function test_appendix_deduplicates_tickets_and_joins_reasons(): void
    {
        // Activo creado dentro del periodo: una sola fila con ambos motivos.
        $this->createAssignedTicket($this->technician, ['state' => 'open', 'title' => 'Activo y creado']);

        $vm = $this->buildReport();

        $this->assertSame(1, $vm->appendixTickets['totalRows']);
        $this->assertSame('Activo · Creado en periodo', $vm->appendixTickets['rows'][0]['inclusionReason']);
    }

    public function test_breakdowns_include_created_in_period_tickets(): void
    {
        // G=1 sin activos ni resueltos: laboratorio y categoría no quedan vacíos.
        $ticket = $this->createAssignedTicket($this->technician, ['state' => 'resolved', 'title' => 'Solo creado']);
        $ticket->forceFill([
            'created_at' => Carbon::parse('2026-06-05 10:00:00'),
            'resolved_at' => Carbon::parse('2026-07-01 10:00:00'),
        ])->save();

        $vm = $this->buildReport();

        $this->assertCount(1, $vm->locationBreakdown);
        $this->assertSame('Laboratorio 1', $vm->locationBreakdown[0]->locationName);
        $this->assertSame(1, $vm->locationBreakdown[0]->createdInPeriod);
        $this->assertSame(0, $vm->locationBreakdown[0]->activeCount);
        $this->assertSame(1, $vm->locationBreakdown[0]->totalRelevant);

        $this->assertCount(1, $vm->categoryBreakdown);
        $this->assertSame('Hardware', $vm->categoryBreakdown[0]->categoryName);
        $this->assertSame(1, $vm->categoryBreakdown[0]->createdInPeriod);
        $this->assertSame(1, $vm->categoryBreakdown[0]->totalRelevant);
        $this->assertSame(100.0, $vm->categoryBreakdown[0]->percentage);
    }

    public function test_breakdown_total_relevant_counts_distinct_tickets(): void
    {
        // Activo creado en el periodo: created=1, active=1, pero total=1.
        $this->createAssignedTicket($this->technician, ['state' => 'open']);

        $vm = $this->buildReport();

        $row = $vm->locationBreakdown[0];
        $this->assertSame(1, $row->activeCount);
        $this->assertSame(1, $row->createdInPeriod);
        $this->assertSame(1, $row->totalRelevant);
    }

    // ── Recomendación agregada según carga real ─────────────────────────────

    public function test_recommendation_without_actives_and_empty_queue_does_not_suggest_closing(): void
    {
        $vm = $this->buildReport();

        $joined = implode(' ', $vm->recommendations);
        $this->assertStringNotContainsString('cierre de los tickets activos', $joined);
        $this->assertStringNotContainsString('asignaciones activas', $joined);
        $this->assertStringContainsString('No hay carga activa asignada ni cierres en el periodo', $joined);
        $this->assertStringContainsString('Mantener disponibilidad', $joined);
    }

    public function test_recommendation_without_actives_but_queue_suggests_global_queue(): void
    {
        $this->createTicket(['state' => 'open', 'title' => 'Disponible en cola']);

        $vm = $this->buildReport();

        $joined = implode(' ', $vm->recommendations);
        $this->assertStringContainsString('evaluar tomar tickets disponibles de la cola global', $joined);
        $this->assertStringNotContainsString('cierre de los tickets activos', $joined);
    }

    public function test_recommendation_with_actives_and_no_closures_prioritises_active_work(): void
    {
        $this->createAssignedTicket($this->technician, ['state' => 'open']);

        $vm = $this->buildReport();

        $joined = implode(' ', $vm->recommendations);
        $this->assertStringContainsString('priorizar el avance y cierre de las asignaciones activas', $joined);
    }

    // ── Recurrencias más allá de las asignaciones activas ───────────────────

    public function test_recurrences_consider_created_in_period_pairs_not_only_active(): void
    {
        $ticket = $this->createAssignedTicket($this->technician, ['state' => 'resolved', 'title' => 'Cerrado fuera']);
        $ticket->forceFill([
            'created_at' => Carbon::parse('2026-06-05 10:00:00'),
            'resolved_at' => Carbon::parse('2026-07-01 10:00:00'),
        ])->save();

        LocationIncidentHistory::create([
            'location_id' => $this->location->id,
            'category_id' => $this->category->id,
            'last_resolved_at' => Carbon::parse('2026-05-20 10:00:00'),
            'recurrence_count' => 3,
            'avg_resolution_time' => '1 día aprox.',
        ]);

        $vm = $this->buildReport();

        $this->assertCount(1, $vm->recurrenceInsights);
        $this->assertSame('Laboratorio 1', $vm->recurrenceInsights[0]->locationName);
        // El promedio histórico string se muestra tal cual, sin recálculo.
        $this->assertSame('1 día aprox.', $vm->recurrenceInsights[0]->avgResolutionLabel);
    }

    // ── Ruido IA y narrativa con poca data ───────────────────────────────────

    public function test_kpis_contain_a_single_ai_row(): void
    {
        $vm = $this->buildReport();

        $aiKpis = array_values(array_filter(
            $vm->kpis,
            static fn (KpiRow $kpi): bool => str_starts_with($kpi->key, 'ai_'),
        ));

        $this->assertCount(1, $aiKpis);
        $this->assertSame('ai_duplicates_active', $aiKpis[0]->key);
        $this->assertTrue($vm->aiSummary['allZero']);
    }

    public function test_low_data_narrative_explains_no_active_load_and_points_to_appendix(): void
    {
        $old = $this->createAssignedTicket($this->technician, ['state' => 'resolved', 'title' => 'Histórico']);
        $old->forceFill([
            'created_at' => Carbon::parse('2026-01-05 10:00:00'),
            'resolved_at' => Carbon::parse('2026-01-06 10:00:00'),
        ])->save();

        $vm = $this->buildReport();

        $joined = implode(' ', $vm->executiveSummary['narrative']);
        $this->assertStringContainsString('No se registra carga operativa activa al momento de generación.', $joined);
        $this->assertStringContainsString('Existe actividad histórica o del periodo asociada al técnico, detallada en el anexo.', $joined);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function buildReport(): MaintenanceReportViewModel
    {
        return MaintenanceReportQuery::for(
            $this->technician,
            DateRange::custom('2026-06-01', '2026-06-15'),
        );
    }

    private function kpi(MaintenanceReportViewModel $vm, string $key): KpiRow
    {
        foreach ($vm->kpis as $kpi) {
            if ($kpi->key === $key) {
                return $kpi;
            }
        }

        $this->fail("KPI no encontrado: {$key}");
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createTicket(array $overrides = []): Ticket
    {
        $reporter = User::factory()->create();

        return Ticket::create(array_merge([
            'title' => 'Ticket de prueba '.Str::random(6),
            'description' => 'Descripción de prueba',
            'reporter_id' => $reporter->id,
            'location_id' => $this->location->id,
            'category_id' => $this->category->id,
            'state' => 'open',
            'priority' => 'medium',
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createAssignedTicket(User $technician, array $overrides = []): Ticket
    {
        $ticket = $this->createTicket($overrides);
        $ticket->forceFill([
            'assigned_to' => $technician->id,
            'assigned_at' => Carbon::now(),
        ])->save();

        return $ticket->refresh();
    }

    private function addTransition(Ticket $ticket, ?string $from, string $to, Carbon $at): StateHistory
    {
        $history = StateHistory::create([
            'ticket_id' => $ticket->id,
            'from_state' => $from,
            'to_state' => $to,
            'changed_by' => $this->technician->id,
            'comment' => null,
        ]);

        $history->forceFill(['created_at' => $at])->save();

        return $history;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createEmbedding(Ticket $ticket, Ticket $matched, array $overrides = []): TicketEmbedding
    {
        return TicketEmbedding::create(array_merge([
            'ticket_id' => $ticket->id,
            'embedding_vector' => [0.1, 0.2, 0.3],
            'description_hash' => hash('sha256', $ticket->embeddingText()),
            'is_duplicate' => true,
            'matched_ticket_id' => $matched->id,
            'similarity_score' => 0.95,
            'review_status' => null,
        ], $overrides));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function sampleStrategyResults(): array
    {
        return [
            [
                'strategy' => 'embedding_similarity',
                'points' => 50,
                'reason' => 'High semantic similarity',
                'metadata' => ['similarity' => 0.95],
                'blocksDuplicate' => false,
                'suggestsRecurrence' => false,
            ],
            [
                'strategy' => 'same_location',
                'points' => 15,
                'reason' => 'Same location',
                'metadata' => [],
                'blocksDuplicate' => false,
                'suggestsRecurrence' => false,
            ],
            [
                'strategy' => 'same_category',
                'points' => 10,
                'reason' => 'Same category',
                'metadata' => [],
                'blocksDuplicate' => false,
                'suggestsRecurrence' => false,
            ],
        ];
    }

    private function createLocation(string $name, string $roomCode): Location
    {
        return Location::create([
            'name' => $name,
            'building' => 'Edificio Z',
            'floor' => '1',
            'room_code' => $roomCode,
            'qr_token' => 'qr-'.strtolower($roomCode),
            'is_active' => true,
        ]);
    }

    private function createCategory(string $name): Category
    {
        return Category::create([
            'name' => $name,
            'icon' => 'wrench',
            'description' => 'Categoría de prueba',
        ]);
    }
}
