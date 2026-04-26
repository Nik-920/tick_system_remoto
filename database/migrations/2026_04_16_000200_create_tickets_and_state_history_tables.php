<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title');
            $table->text('description');
            $table->uuid('reporter_id')->nullable();
            $table->uuid('assigned_to')->nullable();
            $table->uuid('location_id');
            $table->uuid('category_id');
            $table->string('state')->default('open');
            $table->string('priority')->default('medium');
            $table->timestampTz('resolved_at')->nullable();
            $table->timestampsTz();

            $table->foreign('reporter_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('assigned_to')->references('id')->on('users')->nullOnDelete();
            $table->foreign('location_id')->references('id')->on('locations')->cascadeOnDelete();
            $table->foreign('category_id')->references('id')->on('categories')->cascadeOnDelete();

            $table->index(['location_id', 'state']);
            $table->index(['category_id', 'state']);
            $table->index(['reporter_id', 'created_at']);
            $table->index('created_at');
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement("CREATE UNIQUE INDEX tickets_open_in_progress_unique ON tickets (location_id, category_id) WHERE state IN ('open', 'in_progress')");
        }

        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            DB::statement("CREATE UNIQUE INDEX tickets_open_in_progress_unique ON tickets (location_id, category_id) WHERE state IN ('open', 'in_progress')");
        }

        Schema::create('state_history', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('ticket_id');
            $table->string('from_state')->nullable();
            $table->string('to_state');
            $table->uuid('changed_by')->nullable();
            $table->text('comment')->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->foreign('ticket_id')->references('id')->on('tickets')->cascadeOnDelete();
            $table->foreign('changed_by')->references('id')->on('users')->nullOnDelete();

            $table->index(['ticket_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('state_history');

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS tickets_open_in_progress_unique');
        }

        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            DB::statement('DROP INDEX IF EXISTS tickets_open_in_progress_unique');
        }

        Schema::dropIfExists('tickets');
    }
};
