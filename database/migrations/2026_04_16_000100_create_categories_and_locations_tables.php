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
        Schema::create('categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name')->unique();
            $table->string('icon')->nullable();
            $table->text('description')->nullable();
            $table->timestampsTz();
        });

        Schema::create('locations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('building');
            $table->string('floor')->nullable();
            $table->string('room_code')->unique();
            $table->string('qr_token')->unique();
            $table->text('qr_image_url')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
            $table->index(['building', 'floor']);
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('locations');
        Schema::dropIfExists('categories');
    }
};
