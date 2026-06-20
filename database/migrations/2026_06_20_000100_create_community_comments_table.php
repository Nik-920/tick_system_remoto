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
        Schema::create('community_comments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('ticket_id');
            $table->uuid('user_id')->nullable();
            $table->text('body');
            $table->string('status')->default('visible');
            $table->uuid('hidden_by')->nullable();
            $table->timestampTz('hidden_at')->nullable();
            $table->text('hidden_reason')->nullable();
            $table->timestampTz('deleted_at')->nullable();
            $table->timestampsTz();

            $table->foreign('ticket_id')
                ->references('id')
                ->on('tickets')
                ->cascadeOnDelete();

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->foreign('hidden_by')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->index(['ticket_id', 'status', 'created_at'], 'ccomments_ticket_status_created_idx');
            $table->index(['user_id', 'created_at'], 'ccomments_user_created_idx');
            $table->index(['status', 'created_at'], 'ccomments_status_created_idx');
            $table->index(['hidden_by', 'hidden_at'], 'ccomments_hidden_by_at_idx');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE community_comments ENABLE ROW LEVEL SECURITY');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('community_comments');
    }
};
