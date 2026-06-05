-- ╔══════════════════════════════════════════════════════════════════════════════╗
-- ║         SUPABASE RLS — Tick System: Script de Seguridad Completo v2        ║
-- ║                                                                            ║
-- ║  INSTRUCCIONES:                                                            ║
-- ║  1. Ir a Supabase Dashboard → SQL Editor                                  ║
-- ║  2. Pegar TODO el contenido de este archivo                                ║
-- ║  3. Hacer clic en "Run"                                                    ║
-- ║                                                                            ║
-- ║  TABLAS CUBIERTAS (todas las del esquema public):                          ║
-- ║  users, categories, locations, tickets, state_history,                     ║
-- ║  fcm_tokens, notifications, ticket_embeddings, ticket_ai_logs,             ║
-- ║  ticket_media, location_incident_history, personal_access_tokens           ║
-- ╚══════════════════════════════════════════════════════════════════════════════╝

-- ════════════════════════════════════════════════════════
-- PASO 1: HABILITAR RLS EN TODAS LAS TABLAS
-- ════════════════════════════════════════════════════════

ALTER TABLE public.users                        ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.categories                   ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.locations                    ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.tickets                      ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.state_history                ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.fcm_tokens                   ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.notifications                ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.ticket_embeddings            ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.ticket_ai_logs               ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.ticket_media                 ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.location_incident_history    ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.personal_access_tokens       ENABLE ROW LEVEL SECURITY;

-- ════════════════════════════════════════════════════════
-- PASO 2: OTORGAR PERMISOS EXPLÍCITOS (GRANTs)
-- ════════════════════════════════════════════════════════

-- Referencia pública: lecturas sin autenticación permitidas
GRANT SELECT ON public.categories               TO anon, authenticated;
GRANT SELECT ON public.locations                TO anon, authenticated;
GRANT SELECT, INSERT, UPDATE, DELETE ON public.categories            TO service_role;
GRANT SELECT, INSERT, UPDATE, DELETE ON public.locations             TO service_role;

-- Tablas de negocio privadas: solo usuarios autenticados y service_role
GRANT SELECT, INSERT, UPDATE, DELETE ON public.tickets               TO authenticated;
GRANT SELECT                         ON public.state_history         TO authenticated;
GRANT SELECT, INSERT, UPDATE, DELETE ON public.notifications         TO authenticated;
GRANT SELECT, INSERT, UPDATE, DELETE ON public.fcm_tokens            TO authenticated;
GRANT SELECT                         ON public.ticket_embeddings     TO authenticated;
GRANT SELECT                         ON public.ticket_ai_logs        TO authenticated;
GRANT SELECT, INSERT                 ON public.ticket_media          TO authenticated;
GRANT SELECT                         ON public.location_incident_history TO authenticated;
GRANT SELECT                         ON public.users                 TO authenticated;

-- service_role: acceso total a todas las tablas (lo usa Laravel a través del backend)
GRANT SELECT, INSERT, UPDATE, DELETE ON public.tickets               TO service_role;
GRANT SELECT, INSERT, UPDATE, DELETE ON public.state_history         TO service_role;
GRANT SELECT, INSERT, UPDATE, DELETE ON public.notifications         TO service_role;
GRANT SELECT, INSERT, UPDATE, DELETE ON public.fcm_tokens            TO service_role;
GRANT SELECT, INSERT, UPDATE, DELETE ON public.ticket_embeddings     TO service_role;
GRANT SELECT, INSERT, UPDATE, DELETE ON public.ticket_ai_logs        TO service_role;
GRANT SELECT, INSERT, UPDATE, DELETE ON public.ticket_media          TO service_role;
GRANT SELECT, INSERT, UPDATE, DELETE ON public.location_incident_history TO service_role;
GRANT SELECT, INSERT, UPDATE, DELETE ON public.users                 TO service_role;
GRANT SELECT, INSERT, UPDATE, DELETE ON public.personal_access_tokens TO service_role;

-- ════════════════════════════════════════════════════════
-- PASO 3: LIMPIAR POLÍTICAS PREVIAS (evita conflictos)
-- ════════════════════════════════════════════════════════

DROP POLICY IF EXISTS "categories_public_read"              ON public.categories;
DROP POLICY IF EXISTS "categories_anon_read"                ON public.categories;
DROP POLICY IF EXISTS "locations_public_read"               ON public.locations;
DROP POLICY IF EXISTS "locations_anon_read"                 ON public.locations;
DROP POLICY IF EXISTS "tickets_select_own"                  ON public.tickets;
DROP POLICY IF EXISTS "tickets_select_own_or_assigned"      ON public.tickets;
DROP POLICY IF EXISTS "tickets_insert_as_reporter"          ON public.tickets;
DROP POLICY IF EXISTS "tickets_insert_authenticated"        ON public.tickets;
DROP POLICY IF EXISTS "tickets_update_as_assigned"          ON public.tickets;
DROP POLICY IF EXISTS "tickets_update_assigned"             ON public.tickets;
DROP POLICY IF EXISTS "notifications_own"                   ON public.notifications;
DROP POLICY IF EXISTS "notifications_own_rows"              ON public.notifications;
DROP POLICY IF EXISTS "fcm_tokens_own"                      ON public.fcm_tokens;
DROP POLICY IF EXISTS "fcm_tokens_own_rows"                 ON public.fcm_tokens;
DROP POLICY IF EXISTS "users_read_own"                      ON public.users;
DROP POLICY IF EXISTS "state_history_read"                  ON public.state_history;
DROP POLICY IF EXISTS "state_history_read_authenticated"    ON public.state_history;
DROP POLICY IF EXISTS "ticket_embeddings_read"              ON public.ticket_embeddings;
DROP POLICY IF EXISTS "ticket_ai_logs_read"                 ON public.ticket_ai_logs;
DROP POLICY IF EXISTS "ticket_media_read"                   ON public.ticket_media;
DROP POLICY IF EXISTS "ticket_media_insert"                 ON public.ticket_media;
DROP POLICY IF EXISTS "location_history_read"               ON public.location_incident_history;

-- ════════════════════════════════════════════════════════
-- PASO 4: CREAR POLÍTICAS RLS POR TABLA
-- ════════════════════════════════════════════════════════

-- ── CATEGORIES: Lectura pública ──────────────────────────────────────────────
CREATE POLICY "categories_public_read"
ON public.categories FOR SELECT TO authenticated
USING (true);

CREATE POLICY "categories_anon_read"
ON public.categories FOR SELECT TO anon
USING (true);

-- ── LOCATIONS: Lectura pública ───────────────────────────────────────────────
CREATE POLICY "locations_public_read"
ON public.locations FOR SELECT TO authenticated
USING (true);

CREATE POLICY "locations_anon_read"
ON public.locations FOR SELECT TO anon
USING (true);

-- ── TICKETS: Cada actor ve lo que le corresponde ─────────────────────────────
-- Reportero ve sus propios tickets; técnico ve los asignados a él
CREATE POLICY "tickets_select_own"
ON public.tickets FOR SELECT TO authenticated
USING (
    auth.uid()::text = reporter_id::text
    OR auth.uid()::text = assigned_to::text
);

-- Cualquier usuario autenticado puede crear un ticket (él mismo como reporter)
CREATE POLICY "tickets_insert_as_reporter"
ON public.tickets FOR INSERT TO authenticated
WITH CHECK (auth.uid()::text = reporter_id::text);

-- Solo el técnico asignado puede cambiar el estado del ticket
CREATE POLICY "tickets_update_as_assigned"
ON public.tickets FOR UPDATE TO authenticated
USING (auth.uid()::text = assigned_to::text);

-- ── STATE HISTORY: Lectura de historial para autenticados ────────────────────
CREATE POLICY "state_history_read"
ON public.state_history FOR SELECT TO authenticated
USING (true);

-- ── NOTIFICATIONS: Cada usuario solo ve las suyas ────────────────────────────
CREATE POLICY "notifications_own"
ON public.notifications FOR ALL TO authenticated
USING      (auth.uid()::text = user_id::text)
WITH CHECK (auth.uid()::text = user_id::text);

-- ── FCM TOKENS: Cada usuario gestiona solo sus tokens push ───────────────────
CREATE POLICY "fcm_tokens_own"
ON public.fcm_tokens FOR ALL TO authenticated
USING      (auth.uid()::text = user_id::text)
WITH CHECK (auth.uid()::text = user_id::text);

-- ── USERS: Cada usuario autenticado solo puede ver su propia fila ────────────
CREATE POLICY "users_read_own"
ON public.users FOR SELECT TO authenticated
USING (auth.uid()::text = id::text);

-- ── TICKET EMBEDDINGS: Solo lectura para autenticados ────────────────────────
-- Los embeddings de un ticket son visibles si el usuario puede ver el ticket
CREATE POLICY "ticket_embeddings_read"
ON public.ticket_embeddings FOR SELECT TO authenticated
USING (
    EXISTS (
        SELECT 1 FROM public.tickets t
        WHERE t.id = ticket_embeddings.ticket_id
          AND (auth.uid()::text = t.reporter_id::text OR auth.uid()::text = t.assigned_to::text)
    )
);

-- ── TICKET AI LOGS: Solo lectura para autenticados con acceso al ticket ──────
CREATE POLICY "ticket_ai_logs_read"
ON public.ticket_ai_logs FOR SELECT TO authenticated
USING (
    EXISTS (
        SELECT 1 FROM public.tickets t
        WHERE t.id = ticket_ai_logs.ticket_id
          AND (auth.uid()::text = t.reporter_id::text OR auth.uid()::text = t.assigned_to::text)
    )
);

-- ── TICKET MEDIA: Ver y subir archivos solo en tickets del propio usuario ─────
CREATE POLICY "ticket_media_read"
ON public.ticket_media FOR SELECT TO authenticated
USING (
    EXISTS (
        SELECT 1 FROM public.tickets t
        WHERE t.id = ticket_media.ticket_id
          AND (auth.uid()::text = t.reporter_id::text OR auth.uid()::text = t.assigned_to::text)
    )
);

CREATE POLICY "ticket_media_insert"
ON public.ticket_media FOR INSERT TO authenticated
WITH CHECK (
    auth.uid()::text = uploaded_by::text
    AND EXISTS (
        SELECT 1 FROM public.tickets t
        WHERE t.id = ticket_media.ticket_id
          AND auth.uid()::text = t.reporter_id::text
    )
);

-- ── LOCATION INCIDENT HISTORY: Solo lectura para autenticados ────────────────
-- Es una tabla estadística de recurrencias por oficina, sin datos sensibles de usuarios
CREATE POLICY "location_history_read"
ON public.location_incident_history FOR SELECT TO authenticated
USING (true);

-- ── PERSONAL ACCESS TOKENS: Bloqueado totalmente a la API pública ────────────
-- No se crea ninguna política para anon/authenticated intencionalmente.
-- Solo service_role accede a esta tabla (Laravel internamente).


-- ════════════════════════════════════════════════════════
-- VERIFICACIÓN FINAL: Ver estado de RLS por tabla
-- ════════════════════════════════════════════════════════
SELECT
    c.relname                               AS tabla,
    c.relrowsecurity                        AS rls_habilitado,
    c.relforcerowsecurity                   AS rls_forzado
FROM pg_class c
JOIN pg_namespace n ON n.oid = c.relnamespace
WHERE n.nspname = 'public'
  AND c.relkind = 'r'
ORDER BY c.relname;
