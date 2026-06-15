<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2 RLS hardening — extends 2026_05_18_000001_enable_rls_on_all_tables.php.
 *
 * What this migration adds / fixes:
 *
 *  1. Enable RLS on all tables that Phase 1 missed (internal/sensitive tables).
 *     With RLS on and no policy, PostgreSQL denies every row by default — these
 *     tables become invisible to anon / authenticated Supabase API requests.
 *
 *  2. Revoke the over-broad grants that Phase 1 issued to `authenticated` on
 *     ticket_ai_logs and location_incident_history. Those tables already had
 *     deny-by-default protection via RLS (no policy existed), but an unused
 *     GRANT is unnecessary attack surface. We revoke it for clarity.
 *
 *  3. Fix the open state_history SELECT policy. Phase 1 used USING (true),
 *     which lets any authenticated user read every state-history row. Replace it
 *     with a subquery that mirrors the tickets visibility rule (reporter or
 *     assigned).
 *
 *  4. Add ticket_media: enable RLS + SELECT policy for the ticket's reporter
 *     and assigned user. Inserts/updates continue to go through Laravel only.
 *
 *  5. Add a users self-read policy so an authenticated user can read their own
 *     profile row via Supabase API. (Phase 1 issued the SELECT grant but created
 *     no matching policy, so auth users got an empty result set.)
 *
 *  6. Ensure performance indexes that back the RLS subquery joins exist.
 *
 * ARCHITECTURE NOTE:
 *   Laravel connects as the postgres superuser (or a privileged role) and
 *   therefore bypasses RLS entirely. All RLS policies affect only Supabase API
 *   requests (anon / authenticated / service_role PostgREST roles). Laravel's
 *   own authorisation layer (Spatie + Laravel Policies) remains the primary
 *   access control for the application.
 */
return new class extends Migration
{
    protected string $driver;

    public function __construct()
    {
        $this->driver = DB::getDriverName();
    }

    // ──────────────────────────────────────────────────────────────────────────
    // UP
    // ──────────────────────────────────────────────────────────────────────────

    public function up(): void
    {
        if ($this->driver !== 'pgsql') {
            // RLS is a PostgreSQL feature; skip on SQLite (CI / unit tests).
            return;
        }

        $this->enableRlsOnInternalTables();
        $this->revokeOverbroadGrants();
        $this->fixStateHistoryPolicy();
        $this->addTicketMediaPolicy();
        $this->addUsersSelfReadPolicy();
        $this->ensureRlsIndexes();
    }

    // ──────────────────────────────────────────────────────────────────────────
    // DOWN  (development rollback only — restores Phase 1 state)
    // ──────────────────────────────────────────────────────────────────────────

    public function down(): void
    {
        if ($this->driver !== 'pgsql') {
            return;
        }

        // Drop policies introduced by this migration.
        $policyDrops = [
            ['state_history', 'state_history_select_visible_ticket'],
            ['ticket_media',  'ticket_media_select_visible_ticket'],
            ['users',         'users_select_self'],
        ];

        foreach ($policyDrops as [$table, $policy]) {
            DB::statement("DROP POLICY IF EXISTS \"{$policy}\" ON public.{$table}");
        }

        // Restore the original Phase 1 open policy on state_history.
        if (Schema::hasTable('state_history')) {
            DB::statement('DROP POLICY IF EXISTS "state_history_read_authenticated" ON public.state_history');
            DB::statement('
                CREATE POLICY "state_history_read_authenticated"
                ON public.state_history
                FOR SELECT
                TO authenticated
                USING (true)
            ');
        }

        // Restore grants we revoked (Phase 1 had issued them).
        foreach (['ticket_ai_logs', 'location_incident_history'] as $table) {
            if (Schema::hasTable($table)) {
                DB::statement("GRANT SELECT, INSERT, UPDATE, DELETE ON public.{$table} TO authenticated");
                DB::statement("GRANT SELECT, INSERT, UPDATE, DELETE ON public.{$table} TO service_role");
            }
        }

        // Remove the ticket_media SELECT grant we added.
        if (Schema::hasTable('ticket_media')) {
            DB::statement('REVOKE SELECT ON public.ticket_media FROM authenticated');
        }

        // Disable RLS on tables we enabled in this migration (not in Phase 1).
        $tables = [
            'failed_jobs', 'idempotency_keys', 'migrations', 'model_has_permissions',
            'model_has_roles', 'password_reset_tokens', 'permissions', 'personal_access_tokens',
            'role_has_permissions', 'roles', 'sessions', 'ticket_embeddings', 'ticket_media',
        ];

        foreach ($tables as $table) {
            if (Schema::hasTable($table)) {
                DB::statement("ALTER TABLE public.{$table} DISABLE ROW LEVEL SECURITY");
            }
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // PRIVATE STEPS
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Enable RLS on internal/sensitive tables that Phase 1 did not cover.
     *
     * No policies are created for anon or authenticated on these tables.
     * RLS deny-by-default makes them invisible to Supabase API clients.
     * Laravel backend (superuser) is unaffected.
     */
    private function enableRlsOnInternalTables(): void
    {
        $tables = [
            // Laravel internals
            'failed_jobs',
            'migrations',
            'sessions',
            'password_reset_tokens',
            'personal_access_tokens',

            // Idempotency (PostgreSQL is the durable source of truth)
            'idempotency_keys',

            // Spatie permission system
            'roles',
            'permissions',
            'model_has_roles',
            'model_has_permissions',
            'role_has_permissions',

            // AI / ML internals (no client access)
            'ticket_embeddings',

            // Evidence files (needs a targeted policy — added below in addTicketMediaPolicy)
            'ticket_media',
        ];

        foreach ($tables as $table) {
            if (Schema::hasTable($table)) {
                DB::statement("ALTER TABLE public.{$table} ENABLE ROW LEVEL SECURITY");
            }
        }
    }

    /**
     * Revoke grants that Phase 1 issued to authenticated on internal tables.
     *
     * Phase 1 granted SELECT/INSERT/UPDATE/DELETE to `authenticated` on
     * ticket_ai_logs and location_incident_history, but created no RLS policies,
     * so deny-by-default was already blocking access. Revoking the grant removes
     * the risk of accidental policy addition later restoring unintended access.
     */
    private function revokeOverbroadGrants(): void
    {
        foreach (['ticket_ai_logs', 'location_incident_history'] as $table) {
            if (Schema::hasTable($table)) {
                DB::statement("REVOKE ALL ON public.{$table} FROM anon");
                DB::statement("REVOKE ALL ON public.{$table} FROM authenticated");
            }
        }
    }

    /**
     * Fix the state_history SELECT policy.
     *
     * Phase 1 used USING (true) — every authenticated user could read all
     * state-history rows. Replace with a subquery that mirrors the ticket
     * visibility rule: only the reporter or the assigned user may see history.
     */
    private function fixStateHistoryPolicy(): void
    {
        DB::statement('DROP POLICY IF EXISTS "state_history_read_authenticated" ON public.state_history');
        DB::statement('DROP POLICY IF EXISTS "state_history_select_visible_ticket" ON public.state_history');

        if (! Schema::hasTable('state_history')) {
            return;
        }

        DB::statement('
            CREATE POLICY "state_history_select_visible_ticket"
            ON public.state_history
            FOR SELECT
            TO authenticated
            USING (
                EXISTS (
                    SELECT 1
                    FROM public.tickets t
                    WHERE t.id = ticket_id
                      AND (
                        t.reporter_id::text = (SELECT auth.uid()::text)
                        OR t.assigned_to::text = (SELECT auth.uid()::text)
                      )
                )
            )
        ');
    }

    /**
     * Add ticket_media RLS policy.
     *
     * Phase 1 did not enable RLS on this table or add any grants.
     * Now: enable RLS (done in enableRlsOnInternalTables), grant SELECT to
     * authenticated, and allow reads only when the user is the reporter or the
     * assigned technician of the parent ticket.
     *
     * Insert/update/delete stay backend-only (uploaded through Laravel controller).
     */
    private function addTicketMediaPolicy(): void
    {
        if (! Schema::hasTable('ticket_media')) {
            return;
        }

        DB::statement('GRANT SELECT ON public.ticket_media TO authenticated');
        DB::statement('GRANT SELECT, INSERT, UPDATE, DELETE ON public.ticket_media TO service_role');

        DB::statement('DROP POLICY IF EXISTS "ticket_media_select_visible_ticket" ON public.ticket_media');
        DB::statement('
            CREATE POLICY "ticket_media_select_visible_ticket"
            ON public.ticket_media
            FOR SELECT
            TO authenticated
            USING (
                EXISTS (
                    SELECT 1
                    FROM public.tickets t
                    WHERE t.id = ticket_id
                      AND (
                        t.reporter_id::text = (SELECT auth.uid()::text)
                        OR t.assigned_to::text = (SELECT auth.uid()::text)
                      )
                )
            )
        ');
    }

    /**
     * Add users self-read policy.
     *
     * Phase 1 issued GRANT SELECT to authenticated on the users table but
     * created no policy, so authenticated requests returned an empty set.
     * Allow each user to read their own profile row only.
     * Mutations (password, name, avatar, etc.) continue to go through Laravel.
     */
    private function addUsersSelfReadPolicy(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        DB::statement('DROP POLICY IF EXISTS "users_select_self" ON public.users');
        DB::statement('
            CREATE POLICY "users_select_self"
            ON public.users
            FOR SELECT
            TO authenticated
            USING (id::text = (SELECT auth.uid()::text))
        ');
    }

    /**
     * Ensure indexes that back RLS subquery joins exist.
     *
     * The ticket_media and state_history policies do a subquery JOIN on
     * public.tickets. Without indexes on reporter_id and assigned_to the query
     * planner must scan the tickets table for every row returned. These indexes
     * likely already exist from creation migrations; CREATE INDEX IF NOT EXISTS
     * is idempotent.
     */
    private function ensureRlsIndexes(): void
    {
        DB::statement('CREATE INDEX IF NOT EXISTS idx_tickets_reporter_id  ON public.tickets(reporter_id)');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_tickets_assigned_to  ON public.tickets(assigned_to)');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_ticket_media_ticket_id ON public.ticket_media(ticket_id)');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_state_history_ticket_id ON public.state_history(ticket_id)');
    }
};
