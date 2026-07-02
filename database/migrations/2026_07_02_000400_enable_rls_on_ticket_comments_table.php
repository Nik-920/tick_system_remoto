<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ticket_comments (2026_06_25_000800) fue creada después de las migraciones
 * de hardening RLS (2026_05_18 / 2026_06_14) y quedó sin RLS habilitado.
 * Sin grants a anon/authenticated y sin policy: deny-by-default, mismo
 * tratamiento que ticket_ai_logs/idempotency_keys/ticket_embeddings en
 * 2026_06_14_000000_enable_rls_phase2_hardening.php.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        if (Schema::hasTable('ticket_comments')) {
            DB::statement('ALTER TABLE public.ticket_comments ENABLE ROW LEVEL SECURITY');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        if (Schema::hasTable('ticket_comments')) {
            DB::statement('ALTER TABLE public.ticket_comments DISABLE ROW LEVEL SECURITY');
        }
    }
};
