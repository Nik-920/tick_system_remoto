<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds Strategy-engine explainability columns to ticket_embeddings.
     *
     * These columns persist the parallel Strategy scoring produced by
     * DuplicateDetectionEngine so the ticket detail screen can explain WHY
     * the AI flagged a ticket as a possible duplicate.
     *
     * They are AI-managed (written/reset alongside similarity_score and
     * matched_ticket_id). The legacy AI columns and the human-review columns
     * (review_status, reviewed_by, reviewed_at, review_note) are NOT touched.
     *
     *   strategy_score                → total Strategy score for the match (nullable)
     *   strategy_results              → per-strategy breakdown (json, nullable)
     *   strategy_metadata             → aggregated summary (json, nullable)
     *   strategy_suggests_recurrence  → recurrence hint flag (boolean, default false)
     *
     * The json type is portable across PostgreSQL and SQLite.
     */
    public function up(): void
    {
        Schema::table('ticket_embeddings', function (Blueprint $table): void {
            if (! Schema::hasColumn('ticket_embeddings', 'strategy_score')) {
                $table->integer('strategy_score')->nullable()->after('is_duplicate');
            }

            if (! Schema::hasColumn('ticket_embeddings', 'strategy_results')) {
                $table->json('strategy_results')->nullable()->after('strategy_score');
            }

            if (! Schema::hasColumn('ticket_embeddings', 'strategy_metadata')) {
                $table->json('strategy_metadata')->nullable()->after('strategy_results');
            }

            if (! Schema::hasColumn('ticket_embeddings', 'strategy_suggests_recurrence')) {
                $table->boolean('strategy_suggests_recurrence')->default(false)->after('strategy_metadata');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ticket_embeddings', function (Blueprint $table): void {
            $columns = array_values(array_filter([
                'strategy_score',
                'strategy_results',
                'strategy_metadata',
                'strategy_suggests_recurrence',
            ], static fn (string $column): bool => Schema::hasColumn('ticket_embeddings', $column)));

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
