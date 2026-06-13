<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 5.4 — Refinar la clave de deduplicación de notificaciones.
 *
 * dedup_key identifica el evento lógico concreto (incluye from/to state o
 * action), de modo que cambios de estado distintos del mismo ticket el mismo
 * día NO se deduplican entre sí, pero un reintento del mismo evento sí.
 *
 * Si dedup_key es null, NotificationService cae al fallback de la Fase 5.2
 * (user_id + type + ticket_id + created_at::date).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->string('dedup_key')->nullable()->after('ticket_id');
            $table->index(['user_id', 'dedup_key']);
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'dedup_key']);
            $table->dropColumn('dedup_key');
        });
    }
};
