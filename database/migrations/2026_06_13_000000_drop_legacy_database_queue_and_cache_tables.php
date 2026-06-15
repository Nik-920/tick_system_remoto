<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Drops tables that were created for QUEUE_CONNECTION=database and CACHE_STORE=database
 * drivers. Both drivers are now replaced by Redis in all environments:
 *
 *   QUEUE_CONNECTION=redis  → jobs / job_batches tables are unused
 *   CACHE_STORE=redis       → cache / cache_locks tables are unused
 *
 * The failed_jobs table is intentionally kept: QUEUE_FAILED_DRIVER=database-uuids
 * still writes Redis-queue failures to it for traceability.
 *
 * Bus::batch() is not used in this project, so job_batches is safe to remove.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Database-queue table — only needed when QUEUE_CONNECTION=database.
        if (Schema::hasTable('jobs')) {
            Schema::drop('jobs');
        }

        // Batching table — only needed when Bus::batch() is used.
        if (Schema::hasTable('job_batches')) {
            Schema::drop('job_batches');
        }

        // Database-cache tables — only needed when CACHE_STORE=database.
        if (Schema::hasTable('cache')) {
            Schema::drop('cache');
        }

        if (Schema::hasTable('cache_locks')) {
            Schema::drop('cache_locks');
        }
    }

    public function down(): void
    {
        // Recreate cache tables (standard Laravel schema).
        if (! Schema::hasTable('cache')) {
            Schema::create('cache', function (Blueprint $table) {
                $table->string('key')->primary();
                $table->mediumText('value');
                $table->integer('expiration');
            });
        }

        if (! Schema::hasTable('cache_locks')) {
            Schema::create('cache_locks', function (Blueprint $table) {
                $table->string('key')->primary();
                $table->string('owner');
                $table->integer('expiration');
            });
        }

        // Recreate database-queue tables (standard Laravel schema).
        if (! Schema::hasTable('jobs')) {
            Schema::create('jobs', function (Blueprint $table) {
                $table->id();
                $table->string('queue')->index();
                $table->longText('payload');
                $table->unsignedTinyInteger('attempts');
                $table->unsignedInteger('reserved_at')->nullable();
                $table->unsignedInteger('available_at');
                $table->unsignedInteger('created_at');
            });
        }

        if (! Schema::hasTable('job_batches')) {
            Schema::create('job_batches', function (Blueprint $table) {
                $table->string('id')->primary();
                $table->string('name');
                $table->integer('total_jobs');
                $table->integer('pending_jobs');
                $table->integer('failed_jobs');
                $table->longText('failed_job_ids');
                $table->mediumText('options')->nullable();
                $table->integer('cancelled_at')->nullable();
                $table->integer('created_at');
                $table->integer('finished_at')->nullable();
            });
        }
    }
};
