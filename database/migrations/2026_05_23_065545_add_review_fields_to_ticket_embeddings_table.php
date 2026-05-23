<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds manual duplicate-review columns to ticket_embeddings.
     * AI columns (is_duplicate, similarity_score, matched_ticket_id) are NOT touched.
     *
     * review_status values:
     *   null       = no human review yet (pending)
     *   confirmed  = human confirmed the AI decision
     *   dismissed  = human overrode the AI — not a duplicate
     */
    public function up(): void
    {
        Schema::table('ticket_embeddings', function (Blueprint $table): void {
            $table->string('review_status', 20)->nullable()->after('is_duplicate');
            $table->foreignUuid('reviewed_by')->nullable()->after('review_status')
                ->constrained('users')->nullOnDelete();
            $table->timestampTz('reviewed_at')->nullable()->after('reviewed_by');
            $table->text('review_note')->nullable()->after('reviewed_at');

            $table->index('review_status', 'te_review_status_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ticket_embeddings', function (Blueprint $table): void {
            $table->dropIndex('te_review_status_idx');
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn(['review_status', 'reviewed_at', 'review_note']);
        });
    }
};
