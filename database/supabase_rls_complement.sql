-- ╔══════════════════════════════════════════════════════════════════════════════╗
-- ║   SUPABASE RLS — Tick System: Script Complementario (Tablas Restantes)     ║
-- ║                                                                            ║
-- ║  Ejecutar en: Supabase Dashboard → SQL Editor                              ║
-- ║                                                                            ║
-- ║  Este script cubre las tablas internas de Laravel que quedaron             ║
-- ║  sin RLS en la ejecución anterior:                                         ║
-- ║  cache, cache_locks, failed_jobs, job_batches, jobs, migrations,           ║
-- ║  model_has_permissions, model_has_roles, password_reset_tokens,            ║
-- ║  permissions, role_has_permissions, roles, sessions                        ║
-- ╚══════════════════════════════════════════════════════════════════════════════╝


-- ════════════════════════════════════════════════════════
-- PASO 1: HABILITAR RLS en tablas internas de Laravel
-- ESTRATEGIA: Estas tablas son EXCLUSIVAMENTE del backend.
-- Ningún cliente externo (anon/authenticated) debe tocarlas.
-- Solo service_role (que usa Laravel internamente) tiene acceso.
-- ════════════════════════════════════════════════════════

ALTER TABLE public.cache                    ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.cache_locks              ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.failed_jobs              ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.job_batches              ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.jobs                     ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.migrations               ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.model_has_permissions    ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.model_has_roles          ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.password_reset_tokens    ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.permissions              ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.role_has_permissions     ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.roles                    ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.sessions                 ENABLE ROW LEVEL SECURITY;

-- FORCE RLS para las tablas que faltó forzar
ALTER TABLE public.ticket_embeddings        FORCE ROW LEVEL SECURITY;
ALTER TABLE public.ticket_media             FORCE ROW LEVEL SECURITY;
ALTER TABLE public.personal_access_tokens   FORCE ROW LEVEL SECURITY;


-- ════════════════════════════════════════════════════════
-- PASO 2: GRANTs para service_role únicamente
-- Estas tablas son de infraestructura interna.
-- anon y authenticated NO deben tener ningún acceso.
-- ════════════════════════════════════════════════════════

GRANT SELECT, INSERT, UPDATE, DELETE ON public.cache                 TO service_role;
GRANT SELECT, INSERT, UPDATE, DELETE ON public.cache_locks           TO service_role;
GRANT SELECT, INSERT, UPDATE, DELETE ON public.failed_jobs           TO service_role;
GRANT SELECT, INSERT, UPDATE, DELETE ON public.job_batches           TO service_role;
GRANT SELECT, INSERT, UPDATE, DELETE ON public.jobs                  TO service_role;
GRANT SELECT                         ON public.migrations            TO service_role;
GRANT SELECT, INSERT, UPDATE, DELETE ON public.model_has_permissions TO service_role;
GRANT SELECT, INSERT, UPDATE, DELETE ON public.model_has_roles       TO service_role;
GRANT SELECT, INSERT, UPDATE, DELETE ON public.password_reset_tokens TO service_role;
GRANT SELECT                         ON public.permissions           TO service_role;
GRANT SELECT                         ON public.roles                 TO service_role;
GRANT SELECT                         ON public.role_has_permissions  TO service_role;
GRANT SELECT, INSERT, UPDATE, DELETE ON public.sessions              TO service_role;


-- ════════════════════════════════════════════════════════
-- PASO 3: POLÍTICAS RLS — Tablas de infraestructura Laravel
-- Para tablas de infraestructura interna, NO creamos políticas
-- para anon/authenticated. Al activar RLS sin política, el
-- comportamiento por defecto de PostgreSQL es DENEGAR TODO.
-- Esto es exactamente lo que queremos: un "default deny".
-- ════════════════════════════════════════════════════════

-- ── ROLES: Los usuarios autenticados pueden leer su propio rol ───────────────
-- (Útil si el frontend necesita saber el rol del usuario actual)
DROP POLICY IF EXISTS "roles_read_authenticated" ON public.roles;

CREATE POLICY "roles_read_authenticated"
ON public.roles FOR SELECT TO authenticated
USING (true);


-- ── PERMISSIONS: Los usuarios autenticados pueden leer los permisos ──────────
DROP POLICY IF EXISTS "permissions_read_authenticated" ON public.permissions;

CREATE POLICY "permissions_read_authenticated"
ON public.permissions FOR SELECT TO authenticated
USING (true);


-- ── MODEL_HAS_ROLES: Cada usuario ve solo sus propios roles asignados ─────────
DROP POLICY IF EXISTS "model_has_roles_own" ON public.model_has_roles;

CREATE POLICY "model_has_roles_own"
ON public.model_has_roles FOR SELECT TO authenticated
USING (
    model_type = 'App\\Models\\User'
    AND model_id::text = auth.uid()::text
);


-- ── MODEL_HAS_PERMISSIONS: Cada usuario ve solo sus permisos directos ─────────
DROP POLICY IF EXISTS "model_has_permissions_own" ON public.model_has_permissions;

CREATE POLICY "model_has_permissions_own"
ON public.model_has_permissions FOR SELECT TO authenticated
USING (
    model_type = 'App\\Models\\User'
    AND model_id::text = auth.uid()::text
);


-- ── SESSIONS: Cada usuario autenticado solo puede ver su propia sesión ────────
DROP POLICY IF EXISTS "sessions_own" ON public.sessions;

CREATE POLICY "sessions_own"
ON public.sessions FOR SELECT TO authenticated
USING (user_id::text = auth.uid()::text);


-- ── PASSWORD_RESET_TOKENS: Completamente bloqueado a la API pública ───────────
-- Los tokens de reset solo los lee Laravel por email (server-side).
-- No se crea política — comportamiento de "DENEGAR TODO" por defecto.


-- ── CACHE, JOBS, FAILED_JOBS, MIGRATIONS: Solo infraestructura interna ────────
-- Bloqueados totalmente a API pública. Sin políticas = "DENEGAR TODO".


-- ════════════════════════════════════════════════════════
-- VERIFICACIÓN COMPLETA FINAL
-- ════════════════════════════════════════════════════════
SELECT
    c.relname                   AS tabla,
    c.relrowsecurity            AS rls_habilitado,
    c.relforcerowsecurity       AS rls_forzado,
    COUNT(p.polname)            AS num_politicas
FROM pg_class c
JOIN pg_namespace n ON n.oid = c.relnamespace
LEFT JOIN pg_policy p ON p.polrelid = c.oid
WHERE n.nspname = 'public'
  AND c.relkind = 'r'
GROUP BY c.relname, c.relrowsecurity, c.relforcerowsecurity
ORDER BY c.relname;
