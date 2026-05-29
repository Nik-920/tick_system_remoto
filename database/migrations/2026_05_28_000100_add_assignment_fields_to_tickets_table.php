<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->uuid('assigned_by')->nullable()->after('assigned_to');
            $table->timestampTz('assigned_at')->nullable()->after('assigned_by');
            $table->boolean('assignment_locked')->default(false)->after('assigned_at');
            $table->string('assignment_source')->nullable()->after('assignment_locked');

            $table->foreign('assigned_by')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->index(['state', 'assigned_to', 'assignment_locked']);
            $table->index(['assigned_to', 'state']);
            $table->index(['assigned_by']);
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropForeign(['assigned_by']);
            $table->dropIndex(['state', 'assigned_to', 'assignment_locked']);
            $table->dropIndex(['assigned_to', 'state']);
            $table->dropIndex(['assigned_by']);
            $table->dropColumn([
                'assigned_by',
                'assigned_at',
                'assignment_locked',
                'assignment_source',
            ]);
        });
    }
};
