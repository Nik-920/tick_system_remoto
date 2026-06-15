<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Performance indexes missing from previous migrations.
 *
 * notifications(user_id, created_at):
 *   The appNotifications() relation orders by created_at DESC for a specific
 *   user. The existing (user_id, read_at) index does not cover this ORDER BY,
 *   causing a full scan of the user's notifications on every topbar load.
 *
 * tickets(state, created_at):
 *   Admin global KPI queries filter by state alone (no assigned_to), so the
 *   existing (assigned_to, state, created_at) composite is not used. This
 *   composite covers WHERE state = ? ORDER BY created_at.
 *
 * Not added because equivalents already exist:
 *   - tickets(assigned_to, state, created_at)   → maintenance dashboard KPIs
 *   - state_history(ticket_id, to_state, created_at) → in-progress starts
 *   - fcm_tokens UNIQUE(user_id, token)          → token dedup
 *   - notifications(user_id, read_at)            → unread count query
 */
return new class extends Migration
{
    /** @var array<string, list<array{name: string, columns: list<string>}>> */
    private const INDEXES = [
        'notifications' => [
            ['name' => 'idx_notifications_user_created', 'columns' => ['user_id', 'created_at']],
        ],
        'tickets' => [
            ['name' => 'idx_tickets_state_created', 'columns' => ['state', 'created_at']],
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
