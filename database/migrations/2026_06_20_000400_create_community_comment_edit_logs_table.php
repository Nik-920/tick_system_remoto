<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Append-only audit trail for community comment edits.
     *
     * community_comments keeps the current state (body + edited_at); this table
     * preserves the previous/new body of every real edit so admins can audit
     * how a comment changed over time. No updated_at — rows are never modified.
     */
    public function up(): void
    {
        Schema::create('community_comment_edit_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('comment_id');
            $table->uuid('ticket_id');
            $table->uuid('edited_by')->nullable();
            $table->text('previous_body');
            $table->text('new_body');
            $table->json('metadata')->nullable();
            $table->timestampTz('created_at');

            $table->foreign('comment_id')
                ->references('id')
                ->on('community_comments')
                ->cascadeOnDelete();

            $table->foreign('ticket_id')
                ->references('id')
                ->on('tickets')
                ->cascadeOnDelete();

            $table->foreign('edited_by')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->index(['comment_id', 'created_at'], 'ccel_comment_created_idx');
            $table->index(['ticket_id', 'created_at'], 'ccel_ticket_created_idx');
            $table->index(['edited_by', 'created_at'], 'ccel_editor_created_idx');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE community_comment_edit_logs ENABLE ROW LEVEL SECURITY');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('community_comment_edit_logs');
    }
};
