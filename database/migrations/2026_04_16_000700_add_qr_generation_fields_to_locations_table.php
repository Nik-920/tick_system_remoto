<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('locations', function (Blueprint $table): void {
            $table->string('qr_generation_status', 20)
                ->default('pending')
                ->after('qr_image_url');
            $table->text('qr_last_error')->nullable()->after('qr_generation_status');
            $table->string('qr_job_id', 64)->nullable()->after('qr_last_error');
            $table->timestampTz('qr_generated_at')->nullable()->after('qr_job_id');

            $table->index('qr_generation_status');
            $table->index('qr_job_id');
        });

        DB::table('locations')
            ->whereNotNull('qr_image_url')
            ->update([
                'qr_generation_status' => 'ready',
                'qr_generated_at' => DB::raw('updated_at'),
                'qr_last_error' => null,
            ]);

        DB::table('locations')
            ->whereNull('qr_image_url')
            ->update([
                'qr_generation_status' => 'pending',
                'qr_generated_at' => null,
                'qr_last_error' => null,
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('locations', function (Blueprint $table): void {
            $table->dropIndex(['qr_generation_status']);
            $table->dropIndex(['qr_job_id']);
            $table->dropColumn([
                'qr_generation_status',
                'qr_last_error',
                'qr_job_id',
                'qr_generated_at',
            ]);
        });
    }
};
