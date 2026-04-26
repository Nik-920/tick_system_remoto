<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ticket_embeddings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('ticket_id')->unique();
            $table->json('embedding_vector');
            $table->string('description_hash')->nullable();
            $table->double('similarity_score')->nullable();
            $table->uuid('matched_ticket_id')->nullable();
            $table->boolean('is_duplicate')->default(false);
            $table->timestampsTz();

            $table->foreign('ticket_id')->references('id')->on('tickets')->cascadeOnDelete();
            $table->foreign('matched_ticket_id')->references('id')->on('tickets')->nullOnDelete();

            $table->index('is_duplicate');
        });

        Schema::create('ticket_ai_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('ticket_id');
            $table->string('operation_type');
            $table->json('input_data')->nullable();
            $table->json('output_data')->nullable();
            $table->double('confidence_score')->nullable();
            $table->string('action_taken')->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->foreign('ticket_id')->references('id')->on('tickets')->cascadeOnDelete();

            $table->index(['ticket_id', 'operation_type']);
        });

        Schema::create('ticket_media', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('ticket_id');
            $table->text('file_url');
            $table->string('file_type')->default('image');
            $table->uuid('uploaded_by')->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->foreign('ticket_id')->references('id')->on('tickets')->cascadeOnDelete();
            $table->foreign('uploaded_by')->references('id')->on('users')->nullOnDelete();

            $table->index('ticket_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ticket_media');
        Schema::dropIfExists('ticket_ai_logs');
        Schema::dropIfExists('ticket_embeddings');
    }
};
