<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Extends community_reports to support comment-level reports.
     *
     * Semantic contract:
     * - ticket_id is always set (context/parent for queue grouping).
     * - comment_id is null  → report targets the ticket/publication itself.
     * - comment_id not null → report targets that specific comment.
     */
    public function up(): void
    {
        Schema::table('community_reports', function (Blueprint $table) {
            $table->uuid('comment_id')
                ->nullable()
                ->after('ticket_id');

            $table->foreign('comment_id')
                ->references('id')
                ->on('community_comments')
                ->cascadeOnDelete();

            $table->index(
                ['comment_id', 'status', 'created_at'],
                'creports_comment_status_created_idx'
            );
            $table->index(
                ['ticket_id', 'comment_id', 'status'],
                'creports_ticket_comment_status_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::table('community_reports', function (Blueprint $table) {
            $table->dropIndex('creports_ticket_comment_status_idx');
            $table->dropIndex('creports_comment_status_created_idx');
            $table->dropForeign('community_reports_comment_id_foreign');
            $table->dropColumn('comment_id');
        });
    }
};
