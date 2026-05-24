<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('idempotency_keys', function (Blueprint $table): void {
            $table->string('key', 128)->primary();
            $table->uuid('user_id')->nullable()->index();
            $table->string('route', 150)->nullable();
            $table->string('method', 10);
            $table->string('path', 255);
            $table->string('request_hash', 64);
            $table->string('status', 20)->index();
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->json('response_headers')->nullable();
            $table->longText('response_body')->nullable();
            $table->timestampTz('locked_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->timestampTz('failed_at')->nullable();
            $table->timestampTz('expires_at')->index();
            $table->timestampsTz();

            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->index(['user_id', 'status'], 'idem_user_status_idx');
            $table->index(['expires_at', 'status'], 'idem_expires_status_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('idempotency_keys');
    }
};
