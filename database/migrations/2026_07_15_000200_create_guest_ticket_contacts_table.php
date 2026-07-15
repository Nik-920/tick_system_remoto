<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Contacto y código de seguimiento de los reportes QR públicos (invitados).
 * Tabla separada a propósito: la tabla tickets no cambia en nada. Un ticket
 * de invitado es un ticket 100% normal (pertenece al usuario sistema); esta
 * tabla solo guarda el email opcional del invitado y su tracking_code para
 * la página pública de seguimiento.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guest_ticket_contacts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('ticket_id')->unique();
            $table->string('contact_email')->nullable();
            $table->string('tracking_code', 20)->unique();
            $table->timestampsTz();

            $table->foreign('ticket_id')->references('id')->on('tickets')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guest_ticket_contacts');
    }
};
