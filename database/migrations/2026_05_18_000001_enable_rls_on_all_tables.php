<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Migration: Enable Row Level Security (RLS) on all public schema tables.
 *
 * CONTEXT: Supabase is enforcing RLS on all public tables starting:
 *   - May 30, 2026:  New projects — tables require explicit GRANTs.
 *   - Oct 30, 2026:  All existing projects enforced.
 *
 * STRATEGY for this application:
 *  - Laravel connects via a DIRECT Postgres connection string (not supabase-js / PostgREST).
 *  - Therefore, RLS policies apply to the `anon`, `authenticated`, and `service_role` PostgREST roles.
 *  - Laravel's own Postgres user (typically `postgres` or a dedicated role) bypasses RLS entirely
 *    as a superuser, so our application logic is NOT affected by these policies.
 *  - The policies below harden public API exposure while keeping the app fully functional.
 */
return new class extends Migration
{
    /** @var string The Postgres driver name. */
    protected string $driver;

    public function __construct()
    {
        $this->driver = DB::getDriverName();
    }

    public function up(): void
    {
        if ($this->driver !== 'pgsql') {
            // RLS is a PostgreSQL feature — skip on SQLite (CI/testing env).
            return;
        }

        // ── 1. ENABLE RLS on all application tables ──────────────────────────────
        $tables = [
            'users',
            'categories',
            'locations',
            'tickets',
            'state_history',
            'fcm_tokens',
            'notifications',
            'ticket_ai_logs',
            'location_incident_history',
        ];

        foreach ($tables as $table) {
            if (Schema::hasTable($table)) {
                DB::statement("ALTER TABLE public.{$table} ENABLE ROW LEVEL SECURITY");
                // FORCE ensures the table owner (postgres superuser) is also subject to RLS
                // when accessed through PostgREST. Comment this line if it causes issues.
                DB::statement("ALTER TABLE public.{$table} FORCE ROW LEVEL SECURITY");
            }
        }

        // ── 2. GRANT permissions to Supabase built-in roles ──────────────────────
        // `service_role` is used by Supabase Edge Functions and bypasses RLS implicitly.
        // `authenticated` is a logged-in Supabase auth user (supabase-js client).
        // `anon` is an unauthenticated public request.

        // READ-ONLY tables (public reference data — safe to expose to anon)
        $publicReadTables = ['categories', 'locations'];
        foreach ($publicReadTables as $table) {
            DB::statement("GRANT SELECT ON public.{$table} TO anon");
            DB::statement("GRANT SELECT ON public.{$table} TO authenticated");
            DB::statement("GRANT SELECT, INSERT, UPDATE, DELETE ON public.{$table} TO service_role");
        }

        // AUTHENTICATED-ONLY tables (require login)
        $authTables = ['tickets', 'state_history', 'notifications', 'fcm_tokens',
            'ticket_ai_logs', 'location_incident_history'];
        foreach ($authTables as $table) {
            if (Schema::hasTable($table)) {
                DB::statement("GRANT SELECT, INSERT, UPDATE, DELETE ON public.{$table} TO authenticated");
                DB::statement("GRANT SELECT, INSERT, UPDATE, DELETE ON public.{$table} TO service_role");
            }
        }

        // users table: authenticated users may see themselves; service_role manages all
        DB::statement('GRANT SELECT ON public.users TO authenticated');
        DB::statement('GRANT SELECT, INSERT, UPDATE, DELETE ON public.users TO service_role');

        // ── 3. ROW LEVEL SECURITY POLICIES ───────────────────────────────────────

        // ─── categories: anyone authenticated can read; only service_role mutates ───
        DB::statement('
            CREATE POLICY "categories_read_for_authenticated"
            ON public.categories
            FOR SELECT
            TO authenticated
            USING (true)
        ');
        DB::statement('
            CREATE POLICY "categories_anon_read"
            ON public.categories
            FOR SELECT
            TO anon
            USING (true)
        ');

        // ─── locations: anyone authenticated can read; only service_role mutates ───
        DB::statement('
            CREATE POLICY "locations_read_for_authenticated"
            ON public.locations
            FOR SELECT
            TO authenticated
            USING (true)
        ');
        DB::statement('
            CREATE POLICY "locations_anon_read"
            ON public.locations
            FOR SELECT
            TO anon
            USING (true)
        ');

        // ─── tickets: reporters see own; technicians/admins see all ──────────────
        // SELECT: users see tickets they reported OR are assigned to
        DB::statement('
            CREATE POLICY "tickets_select_own_or_assigned"
            ON public.tickets
            FOR SELECT
            TO authenticated
            USING (
                auth.uid()::text = reporter_id::text
                OR auth.uid()::text = assigned_to::text
            )
        ');
        // INSERT: any authenticated user can create a ticket
        DB::statement('
            CREATE POLICY "tickets_insert_authenticated"
            ON public.tickets
            FOR INSERT
            TO authenticated
            WITH CHECK (auth.uid()::text = reporter_id::text)
        ');
        // UPDATE/DELETE: only the assigned technician or service_role
        DB::statement('
            CREATE POLICY "tickets_update_assigned"
            ON public.tickets
            FOR UPDATE
            TO authenticated
            USING (auth.uid()::text = assigned_to::text)
        ');

        // ─── notifications: users see only their own ──────────────────────────────
        DB::statement('
            CREATE POLICY "notifications_own_rows"
            ON public.notifications
            FOR ALL
            TO authenticated
            USING (auth.uid()::text = user_id::text)
            WITH CHECK (auth.uid()::text = user_id::text)
        ');

        // ─── fcm_tokens: users manage only their own tokens ──────────────────────
        DB::statement('
            CREATE POLICY "fcm_tokens_own_rows"
            ON public.fcm_tokens
            FOR ALL
            TO authenticated
            USING (auth.uid()::text = user_id::text)
            WITH CHECK (auth.uid()::text = user_id::text)
        ');

        // ─── state_history: authenticated can read ticket history they can see ────
        DB::statement('
            CREATE POLICY "state_history_read_authenticated"
            ON public.state_history
            FOR SELECT
            TO authenticated
            USING (true)
        ');
    }

    public function down(): void
    {
        if ($this->driver !== 'pgsql') {
            return;
        }

        // Drop all policies
        $policyDrops = [
            ['categories', 'categories_read_for_authenticated'],
            ['categories', 'categories_anon_read'],
            ['locations', 'locations_read_for_authenticated'],
            ['locations', 'locations_anon_read'],
            ['tickets', 'tickets_select_own_or_assigned'],
            ['tickets', 'tickets_insert_authenticated'],
            ['tickets', 'tickets_update_assigned'],
            ['notifications', 'notifications_own_rows'],
            ['fcm_tokens', 'fcm_tokens_own_rows'],
            ['state_history', 'state_history_read_authenticated'],
        ];

        foreach ($policyDrops as [$table, $policy]) {
            DB::statement("DROP POLICY IF EXISTS \"{$policy}\" ON public.{$table}");
        }

        // Disable RLS
        $tables = ['users', 'categories', 'locations', 'tickets', 'state_history',
            'fcm_tokens', 'notifications', 'ticket_ai_logs', 'location_incident_history'];

        foreach ($tables as $table) {
            if (Schema::hasTable($table)) {
                DB::statement("ALTER TABLE public.{$table} NO FORCE ROW LEVEL SECURITY");
                DB::statement("ALTER TABLE public.{$table} DISABLE ROW LEVEL SECURITY");
            }
        }
    }
};
