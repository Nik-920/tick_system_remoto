<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_ai_logs', function (Blueprint $table) {
            $table->uuid('correlation_id')->nullable()->after('operation_type');
            $table->index('correlation_id');
        });
    }

    public function down(): void
    {
        Schema::table('ticket_ai_logs', function (Blueprint $table) {
            $table->dropIndex(['correlation_id']);
            $table->dropColumn('correlation_id');
        });
    }
};
