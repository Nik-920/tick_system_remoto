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
        Schema::create('community_reports', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('ticket_id');
            $table->uuid('reported_by')->nullable();
            $table->string('reason');
            $table->text('note')->nullable();
            $table->string('status')->default('pending');
            $table->uuid('reviewed_by')->nullable();
            $table->timestampTz('reviewed_at')->nullable();
            $table->text('resolution_note')->nullable();
            $table->timestampsTz();

            $table->foreign('ticket_id')
                ->references('id')
                ->on('tickets')
                ->cascadeOnDelete();

            $table->foreign('reported_by')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->foreign('reviewed_by')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->index(['ticket_id', 'status', 'created_at'], 'creports_ticket_status_created_idx');
            $table->index(['reported_by', 'created_at'], 'creports_reporter_created_idx');
            $table->index(['status', 'created_at'], 'creports_status_created_idx');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE community_reports ENABLE ROW LEVEL SECURITY');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('community_reports');
    }
};
