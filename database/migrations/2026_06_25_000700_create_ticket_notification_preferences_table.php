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
        Schema::create('ticket_notification_preferences', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->string('type', 80);
            $table->string('channel', 20);
            $table->boolean('enabled')->default(true);
            $table->timestampsTz();

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();

            $table->unique(['user_id', 'type', 'channel'], 'tnp_user_type_channel_unique');
            $table->index(['type', 'channel', 'enabled'], 'tnp_type_channel_enabled_idx');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE ticket_notification_preferences ENABLE ROW LEVEL SECURITY');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_notification_preferences');
    }
};
