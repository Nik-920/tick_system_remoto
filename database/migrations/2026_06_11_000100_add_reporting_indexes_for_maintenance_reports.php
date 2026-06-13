<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reporting indexes for the professional maintenance report (and future
 * Admin/Super reports). Plain composite b-tree indexes via Schema Builder:
 * portable between SQLite (tests) and PostgreSQL/Supabase, no partial or
 * Postgres-only indexes.
 *
 * Not added because an equivalent already exists:
 * - tickets (state, assigned_to, assignment_locked)  → global queue.
 * - tickets (assigned_to, state)                     → covered by the new
 *   (assigned_to, state, created_at).
 * - state_history (ticket_id, created_at)            → history per ticket.
 * - ticket_media (ticket_id)                         → evidence counts.
 * - location_incident_history UNIQUE (location_id, category_id) → recurrences.
 */
return new class extends Migration
{
    /** @var array<string, list<array{name: string, columns: list<string>}>> */
    private const INDEXES = [
        'tickets' => [
            ['name' => 'idx_tickets_assigned_state_created', 'columns' => ['assigned_to', 'state', 'created_at']],
            ['name' => 'idx_tickets_assigned_resolved_at', 'columns' => ['assigned_to', 'resolved_at']],
            ['name' => 'idx_tickets_assigned_created_at', 'columns' => ['assigned_to', 'created_at']],
            ['name' => 'idx_tickets_location_created', 'columns' => ['location_id', 'created_at']],
            ['name' => 'idx_tickets_category_created', 'columns' => ['category_id', 'created_at']],
        ],
        'state_history' => [
            ['name' => 'idx_state_history_ticket_state_created', 'columns' => ['ticket_id', 'to_state', 'created_at']],
            ['name' => 'idx_state_history_to_state_created', 'columns' => ['to_state', 'created_at']],
        ],
    ];

    public function up(): void
    {
        foreach (self::INDEXES as $table => $indexes) {
            Schema::table($table, function (Blueprint $blueprint) use ($table, $indexes): void {
                foreach ($indexes as $index) {
                    if (! Schema::hasIndex($table, $index['name'])) {
                        $blueprint->index($index['columns'], $index['name']);
                    }
                }
            });
        }
    }

    public function down(): void
    {
        foreach (self::INDEXES as $table => $indexes) {
            Schema::table($table, function (Blueprint $blueprint) use ($table, $indexes): void {
                foreach ($indexes as $index) {
                    if (Schema::hasIndex($table, $index['name'])) {
                        $blueprint->dropIndex($index['name']);
                    }
                }
            });
        }
    }
};
