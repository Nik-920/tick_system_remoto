<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Location;
use App\Models\LocationIncidentHistory;
use App\Models\StateHistory;
use App\Models\Ticket;
use App\Models\TicketEmbedding;
use App\Models\TicketMedia;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

/**
 * Demo dataset to exercise the professional maintenance report with realistic
 * scenarios: active load, stale tickets, period closures, administrative
 * closures, global queue, evidence coverage, historical recurrences and an
 * AI-duplicate signal.
 *
 * Local/staging only: it refuses to run in production. Idempotent: tickets
 * are keyed by title, users by email, locations by room_code and categories
 * by name, so re-running it does not duplicate data.
 *
 * Usage: php artisan db:seed --class=MaintenanceReportScenarioSeeder
 */
class MaintenanceReportScenarioSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command?->warn('MaintenanceReportScenarioSeeder omitido: no se ejecuta en producción.');

            return;
        }

        foreach (['reporter', 'maintenance', 'admin', 'super_admin'] as $role) {
            Role::findOrCreate($role, 'web');
        }

        $now = CarbonImmutable::now();

        // ── Users ────────────────────────────────────────────────────────────
        $technician = $this->user('tecnico.informe@incidex.test', 'Técnico Informe', 'maintenance');
        $otherTechnician = $this->user('tecnico.otro@incidex.test', 'Técnico Ajeno', 'maintenance');
        $reporter = $this->user('reporter.informe@incidex.test', 'Reporter Informe', 'reporter');

        // ── Catalogues ───────────────────────────────────────────────────────
        $lab1 = $this->location('Laboratorio 1', 'LAB-101');
        $lab2 = $this->location('Laboratorio 2', 'LAB-202');
        $lab3 = $this->location('Laboratorio 3', 'LAB-303');

        $infra = $this->category('Infraestructura');
        $network = $this->category('Red y Conectividad');
        $hardware = $this->category('Hardware');
        $software = $this->category('Software');

        // ── Active load of the technician (snapshot) ─────────────────────────
        $openRecent = $this->ticket('Demo · Abierto reciente', $reporter, $lab1, $hardware, [
            'state' => Ticket::STATE_OPEN,
            'priority' => 'medium',
            'created_at' => $now->subDay(),
        ], $technician);

        $inProgress = $this->ticket('Demo · En progreso con evidencia', $reporter, $lab1, $network, [
            'state' => Ticket::STATE_IN_PROGRESS,
            'priority' => 'medium',
            'created_at' => $now->subDays(4),
        ], $technician);
        $this->transition($inProgress, Ticket::STATE_OPEN, Ticket::STATE_IN_PROGRESS, $now->subDays(3), $technician);
        $this->media($inProgress, $technician);

        $staleOpen = $this->ticket('Demo · Abierto atrasado +3 días', $reporter, $lab2, $infra, [
            'state' => Ticket::STATE_OPEN,
            'priority' => 'medium',
            'created_at' => $now->subDays(6),
        ], $technician);

        $high = $this->ticket('Demo · Prioridad alta activa', $reporter, $lab2, $software, [
            'state' => Ticket::STATE_OPEN,
            'priority' => 'high',
            'created_at' => $now->subDays(2),
        ], $technician);

        $critical = $this->ticket('Demo · Crítico activo', $reporter, $lab3, $infra, [
            'state' => Ticket::STATE_IN_PROGRESS,
            'priority' => 'critical',
            'created_at' => $now->subDays(2),
        ], $technician);
        $this->transition($critical, Ticket::STATE_OPEN, Ticket::STATE_IN_PROGRESS, $now->subDay(), $technician);

        // ── Period closures ──────────────────────────────────────────────────
        $resolvedInPeriod = $this->ticket('Demo · Resuelto dentro del periodo', $reporter, $lab1, $hardware, [
            'state' => Ticket::STATE_RESOLVED,
            'priority' => 'medium',
            'created_at' => $now->subDays(10),
            'resolved_at' => $now->subDays(8),
        ], $technician);
        $this->transition($resolvedInPeriod, Ticket::STATE_OPEN, Ticket::STATE_IN_PROGRESS, $now->subDays(9), $technician);
        $this->transition($resolvedInPeriod, Ticket::STATE_IN_PROGRESS, Ticket::STATE_RESOLVED, $now->subDays(8), $technician);
        $this->media($resolvedInPeriod, $technician);

        $resolvedOutside = $this->ticket('Demo · Resuelto fuera del periodo', $reporter, $lab2, $network, [
            'state' => Ticket::STATE_RESOLVED,
            'priority' => 'low',
            'created_at' => $now->subDays(90),
            'resolved_at' => $now->subDays(85),
        ], $technician);
        $this->transition($resolvedOutside, Ticket::STATE_IN_PROGRESS, Ticket::STATE_RESOLVED, $now->subDays(85), $technician);

        $rejected = $this->ticket('Demo · Rechazado en periodo', $reporter, $lab3, $software, [
            'state' => Ticket::STATE_REJECTED,
            'priority' => 'low',
            'created_at' => $now->subDays(7),
        ], $technician);
        $this->transition($rejected, Ticket::STATE_OPEN, Ticket::STATE_REJECTED, $now->subDays(5), $technician);

        $cancelled = $this->ticket('Demo · Cancelado por reporter', $reporter, $lab1, $software, [
            'state' => Ticket::STATE_CANCELLED,
            'priority' => 'medium',
            'created_at' => $now->subDays(6),
        ], $technician);
        $this->transition($cancelled, Ticket::STATE_OPEN, Ticket::STATE_CANCELLED, $now->subDays(4), $reporter);

        // ── Isolation: load of another technician (must never leak) ─────────
        $this->ticket('Demo · Carga de otro técnico', $reporter, $lab2, $hardware, [
            'state' => Ticket::STATE_OPEN,
            'priority' => 'medium',
            'created_at' => $now->subDays(3),
        ], $otherTechnician);

        // ── Global queue context ─────────────────────────────────────────────
        $this->ticket('Demo · Cola global disponible', $reporter, $lab3, $network, [
            'state' => Ticket::STATE_OPEN,
            'priority' => 'medium',
            'created_at' => $now->subDays(2),
        ]);

        $this->ticket('Demo · Bloqueado fuera de cola', $reporter, $lab3, $infra, [
            'state' => Ticket::STATE_OPEN,
            'priority' => 'medium',
            'assignment_locked' => true,
            'created_at' => $now->subDays(2),
        ]);

        // ── Historical recurrence for an assigned pair ───────────────────────
        LocationIncidentHistory::query()->firstOrCreate(
            ['location_id' => $lab1->id, 'category_id' => $hardware->id],
            [
                'last_resolved_at' => $now->subDays(20),
                'recurrence_count' => 3,
                'avg_resolution_time' => '2 días aprox.',
            ],
        );

        // ── AI duplicate signal on an active ticket ──────────────────────────
        TicketEmbedding::query()->firstOrCreate(
            ['ticket_id' => $openRecent->id],
            [
                'embedding_vector' => [0.1, 0.2, 0.3],
                'description_hash' => hash('sha256', $openRecent->embeddingText()),
                'is_duplicate' => true,
                'matched_ticket_id' => $resolvedInPeriod->id,
                'similarity_score' => 0.93,
                'strategy_score' => 70,
                'review_status' => null,
                'strategy_results' => [
                    [
                        'strategy' => 'embedding_similarity',
                        'points' => 50,
                        'reason' => 'High semantic similarity',
                        'metadata' => ['similarity' => 0.93],
                        'blocksDuplicate' => false,
                        'suggestsRecurrence' => false,
                    ],
                    [
                        'strategy' => 'same_location',
                        'points' => 20,
                        'reason' => 'Same location',
                        'metadata' => [],
                        'blocksDuplicate' => false,
                        'suggestsRecurrence' => false,
                    ],
                ],
            ],
        );

        $this->command?->info('Escenario demo del informe de mantenimiento sembrado: técnico tecnico.informe@incidex.test (password: password).');
    }

    private function user(string $email, string $name, string $role): User
    {
        $user = User::query()->firstOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ],
        );

        if (! $user->hasRole($role)) {
            $user->assignRole($role);
        }

        return $user;
    }

    private function location(string $name, string $roomCode): Location
    {
        return Location::query()->firstOrCreate(
            ['room_code' => $roomCode],
            [
                'name' => $name,
                'building' => 'Edificio Demo',
                'floor' => '1',
                'qr_token' => 'qr-demo-'.strtolower($roomCode),
                'is_active' => true,
            ],
        );
    }

    private function category(string $name): Category
    {
        return Category::query()->firstOrCreate(
            ['name' => $name],
            ['icon' => 'wrench', 'description' => 'Categoría demo del informe'],
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function ticket(
        string $title,
        User $reporter,
        Location $location,
        Category $category,
        array $attributes,
        ?User $assignee = null,
    ): Ticket {
        $existing = Ticket::query()->where('title', $title)->first();
        if ($existing !== null) {
            return $existing;
        }

        $ticket = Ticket::create([
            'title' => $title,
            'description' => 'Escenario demo del informe profesional de mantenimiento.',
            'reporter_id' => $reporter->id,
            'location_id' => $location->id,
            'category_id' => $category->id,
            'state' => $attributes['state'] ?? Ticket::STATE_OPEN,
            'priority' => $attributes['priority'] ?? 'medium',
            'assignment_locked' => (bool) ($attributes['assignment_locked'] ?? false),
        ]);

        $fill = [];
        if (isset($attributes['created_at'])) {
            $fill['created_at'] = $attributes['created_at'];
        }
        if (isset($attributes['resolved_at'])) {
            $fill['resolved_at'] = $attributes['resolved_at'];
        }
        if ($assignee !== null) {
            $fill['assigned_to'] = $assignee->id;
            $fill['assigned_at'] = $attributes['created_at'] ?? now();
        }

        if ($fill !== []) {
            $ticket->forceFill($fill)->save();
        }

        return $ticket->refresh();
    }

    private function transition(Ticket $ticket, ?string $from, string $to, CarbonImmutable $at, User $by): void
    {
        $exists = StateHistory::query()
            ->where('ticket_id', $ticket->id)
            ->where('to_state', $to)
            ->exists();

        if ($exists) {
            return;
        }

        $history = StateHistory::create([
            'ticket_id' => $ticket->id,
            'from_state' => $from,
            'to_state' => $to,
            'changed_by' => $by->id,
            'comment' => 'Transición demo del informe',
        ]);

        $history->forceFill(['created_at' => $at])->save();
    }

    private function media(Ticket $ticket, User $uploader): void
    {
        TicketMedia::query()->firstOrCreate(
            ['ticket_id' => $ticket->id, 'file_url' => 'https://demo.incidex.test/evidencias/'.$ticket->id.'.jpg'],
            ['file_type' => 'image', 'uploaded_by' => $uploader->id],
        );
    }
}
