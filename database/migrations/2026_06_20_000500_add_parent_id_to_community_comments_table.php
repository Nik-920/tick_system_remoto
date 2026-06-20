<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adds one-level reply support to community_comments.
     *
     * Semantic contract:
     * - parent_id is null     → root comment.
     * - parent_id is not null → reply to a root comment (depth is capped at 1
     *   by the controller, which forbids replying to a comment that already
     *   has a parent).
     *
     * Self-referential FK is added with the same explicit pattern used for the
     * nullable comment_id on community_reports — portable across pgsql/sqlite.
     */
    public function up(): void
    {
        Schema::table('community_comments', function (Blueprint $table) {
            $table->uuid('parent_id')
                ->nullable()
                ->after('ticket_id');

            $table->foreign('parent_id')
                ->references('id')
                ->on('community_comments')
                ->cascadeOnDelete();

            $table->index(
                ['ticket_id', 'parent_id', 'status', 'created_at'],
                'ccomments_ticket_parent_status_created_idx'
            );
            $table->index(
                ['parent_id', 'status', 'created_at'],
                'ccomments_parent_status_created_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::table('community_comments', function (Blueprint $table) {
            $table->dropIndex('ccomments_parent_status_created_idx');
            $table->dropIndex('ccomments_ticket_parent_status_created_idx');
            $table->dropForeign('community_comments_parent_id_foreign');
            $table->dropColumn('parent_id');
        });
    }
};
