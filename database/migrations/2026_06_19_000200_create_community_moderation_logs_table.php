<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('community_moderation_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('ticket_id');
            $table->string('action');
            $table->text('reason')->nullable();
            $table->uuid('performed_by')->nullable();
            $table->boolean('previous_visible')->nullable();
            $table->boolean('new_visible')->nullable();
            $table->text('previous_reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestampTz('created_at');

            $table->foreign('ticket_id')
                ->references('id')
                ->on('tickets')
                ->cascadeOnDelete();

            $table->foreign('performed_by')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->index(['ticket_id', 'created_at'], 'cml_ticket_created_idx');
            $table->index(['action', 'created_at'], 'cml_action_created_idx');
            $table->index(['performed_by', 'created_at'], 'cml_performer_created_idx');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE community_moderation_logs ENABLE ROW LEVEL SECURITY');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('community_moderation_logs');
    }
};
