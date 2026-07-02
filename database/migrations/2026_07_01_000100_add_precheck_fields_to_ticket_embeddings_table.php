<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds a third, independent column lane to ticket_embeddings: the
     * reporter-facing duplicate PRECHECK (DuplicatePrecheckService), which
     * runs synchronously at ticket-creation time — before any embedding or
     * AI strategy score exists.
     *
     * This lane is never written by DetectDuplicates/GenerateTicketEmbedding
     * (the AI lane: similarity_score, matched_ticket_id, is_duplicate,
     * strategy_*) and never written by the admin review action (the human
     * lane: review_status, reviewed_by, reviewed_at, review_note). It records,
     * permanently, which candidate ticket the reporter was warned about and
     * chose to proceed past ("este es un caso distinto"), so that context is
     * not lost even when the (stricter, independent) AI check later finds no
     * match of its own and clears its own columns.
     *
     *   precheck_matched_ticket_id → the candidate ticket flagged by the precheck
     *   precheck_reason            → human-readable reason ("Misma ubicación...")
     *   precheck_confirmed_at      → when the reporter confirmed past the warning
     */
    public function up(): void
    {
        Schema::table('ticket_embeddings', function (Blueprint $table): void {
            $table->uuid('precheck_matched_ticket_id')->nullable()->after('strategy_suggests_recurrence');
            $table->string('precheck_reason')->nullable()->after('precheck_matched_ticket_id');
            $table->timestampTz('precheck_confirmed_at')->nullable()->after('precheck_reason');

            $table->foreign('precheck_matched_ticket_id', 'te_precheck_matched_ticket_fk')
                ->references('id')->on('tickets')->nullOnDelete();

            $table->index('precheck_matched_ticket_id', 'te_precheck_matched_ticket_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ticket_embeddings', function (Blueprint $table): void {
            $table->dropIndex('te_precheck_matched_ticket_idx');
            $table->dropForeign('te_precheck_matched_ticket_fk');
            $table->dropColumn(['precheck_matched_ticket_id', 'precheck_reason', 'precheck_confirmed_at']);
        });
    }
};
