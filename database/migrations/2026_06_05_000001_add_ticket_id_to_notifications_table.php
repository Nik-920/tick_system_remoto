<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 5.2 — Idempotencia de notificaciones in-app.
 *
 * Añade ticket_id a notifications para poder deduplicar por
 * (user_id, type, ticket_id, created_at::date) antes de crear una notificación,
 * evitando duplicados cuando un listener encolado se reintenta.
 *
 * Sin FK: SQLite (entorno de tests) no soporta añadir foreign keys vía ALTER
 * TABLE, y la deduplicación solo necesita la columna + índice, no integridad
 * referencial. Se sigue el mismo precedente que la columna correlation_id de
 * ticket_ai_logs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->uuid('ticket_id')->nullable()->after('user_id');
            $table->index(['user_id', 'type', 'ticket_id']);
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'type', 'ticket_id']);
            $table->dropColumn('ticket_id');
        });
    }
};
