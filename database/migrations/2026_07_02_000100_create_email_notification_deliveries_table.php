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
        Schema::create('email_notification_deliveries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->nullable();
            $table->uuid('ticket_id')->nullable();
            $table->string('notification_type', 100);
            $table->string('channel', 20)->default('email');
            $table->string('recipient_email', 255);
            $table->string('resend_email_id')->nullable()->unique();
            $table->string('resend_message_id')->nullable();
            $table->string('subject', 255);
            $table->string('status', 50)->default('queued');
            $table->string('dedup_key')->nullable();
            $table->string('last_event_type')->nullable();
            $table->timestampTz('last_event_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestampTz('sent_at')->nullable();
            $table->timestampTz('delivered_at')->nullable();
            $table->timestampTz('bounced_at')->nullable();
            $table->timestampTz('complained_at')->nullable();
            $table->timestampTz('opened_at')->nullable();
            $table->timestampTz('clicked_at')->nullable();
            $table->timestampsTz();

            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('ticket_id')->references('id')->on('tickets')->cascadeOnDelete();

            $table->index(['user_id', 'notification_type', 'ticket_id', 'created_at'], 'email_deliv_dedup_idx');
            $table->index('status', 'email_deliv_status_idx');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE email_notification_deliveries ENABLE ROW LEVEL SECURITY');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('email_notification_deliveries');
    }
};
