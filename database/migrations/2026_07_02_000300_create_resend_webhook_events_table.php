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
        Schema::create('resend_webhook_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('svix_id')->unique();
            $table->string('event_type', 50);
            $table->timestampTz('received_at');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE resend_webhook_events ENABLE ROW LEVEL SECURITY');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('resend_webhook_events');
    }
};
