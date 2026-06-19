<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->boolean('community_visible')->default(true)->after('assignment_source');
            $table->timestampTz('community_hidden_at')->nullable()->after('community_visible');
            $table->uuid('community_hidden_by')->nullable()->after('community_hidden_at');
            $table->string('community_visibility_reason')->nullable()->after('community_hidden_by');

            $table->foreign('community_hidden_by')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->index(
                ['community_visible', 'state', 'updated_at'],
                'tickets_community_feed_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropForeign(['community_hidden_by']);
            $table->dropIndex('tickets_community_feed_idx');
            $table->dropColumn([
                'community_visible',
                'community_hidden_at',
                'community_hidden_by',
                'community_visibility_reason',
            ]);
        });
    }
};
