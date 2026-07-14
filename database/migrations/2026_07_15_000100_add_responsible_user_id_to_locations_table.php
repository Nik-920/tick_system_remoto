<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jefe de Práctica responsable por ubicación (feedback de Soporte Técnico):
 * cada laboratorio/aula puede tener un usuario maintenance encargado. Nullable
 * a propósito: las ubicaciones existentes no cambian de comportamiento y el
 * pool de tickets (claim/release) sigue siendo el fallback cuando no hay
 * responsable configurado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->uuid('responsible_user_id')->nullable()->after('room_code');
            $table->foreign('responsible_user_id')->references('id')->on('users')->nullOnDelete();
            $table->index('responsible_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->dropForeign(['responsible_user_id']);
            $table->dropIndex(['responsible_user_id']);
            $table->dropColumn('responsible_user_id');
        });
    }
};
