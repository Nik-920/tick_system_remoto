<div align="center">

# 🎫 Sistema de Reporte de Incidencias de Infraestructura

### *Infrastructure Ticketing System*

[![Laravel](https://img.shields.io/badge/Laravel-12.56.0-FF2D20?style=for-the-badge&logo=laravel&logoColor=white)](https://laravel.com)
[![Supabase](https://img.shields.io/badge/Supabase-Database-3ECF8E?style=for-the-badge&logo=supabase&logoColor=white)](https://supabase.com)
[![PHP](https://img.shields.io/badge/PHP-8.2.12-777BB4?style=for-the-badge&logo=php&logoColor=white)](https://php.net)
[![Tailwind CSS](https://img.shields.io/badge/Tailwind_CSS-3.x-38B2AC?style=for-the-badge&logo=tailwind-css&logoColor=white)](https://tailwindcss.com)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg?style=for-the-badge)](LICENSE)

**Proyecto académico para los cursos de Desarrollo de Aplicaciones DevOps, Testing y Aseguramiento de la Calidad en Desarrollo de Software**

 [Instalación](#-instalación-y-configuración) · [Decisiones de Arquitectura](#-decisiones-de-arquitectura-y-mejores-prácticas) · [Patrones de Diseño](#-patrones-de-diseño-aplicados) · [API](#-referencia-de-la-api) · [Testing](#-estrategia-de-testing)

[Estado Actual del Proyecto](Docs/ESTADO_ACTUAL_PROYECTO.md) · [Plan Completo de Cierre](Docs/PLAN_CIERRE_PROYECTO.md) · [Implementacion de Tablero P0 P1 P2](Docs/TABLERO_TRABAJO_P0_P1_P2.md) · [Activacion Sentry Staging y Produccion](Docs/SENTRY_ACTIVACION_STAGING_PROD.md)

</div>

---

## 📋 Tabla de Contenidos

## 📖 Descripción del Proyecto

**Sistema de Reporte de Incidencias de Infraestructura** es una aplicación web que permite a alumnos y profesores reportar fallas de infraestructura (proyectores averiados, baños en mal estado, enchufes sin corriente, etc.) escaneando un **código QR** ubicado en cada aula o espacio físico.

### El Problema

> Un proyector no funciona, un baño está malogrado o un enchufe no da corriente, y el equipo de mantenimiento tarda semanas en enterarse porque los reportes llegan de forma informal, se pierden o nunca se registran adecuadamente.

### La Solución

Una plataforma centralizada donde:

- 📱 **Alumnos y docentes** escanean el QR del espacio afectado y crean un ticket en segundos.
- 🔧 **Mantenimiento** gestiona los tickets con estados bien definidos (*Abierto → En Proceso → Resuelto*).
- 👑 **Administradores** tienen visibilidad total, métricas y control de usuarios.
- 🔒 **Autenticación** segura a través de Supabase Auth.

---

## ⚖️ Análisis Crítico — Pros y Contras

Antes de escribir una sola línea de código, es fundamental analizar objetivamente las limitaciones del enfoque propuesto para mitigarlas desde el diseño.

### ✅ Puntos a Favor

| Aspecto | Detalle |
|---|---|
| **Bajo costo de adopción** | Escanear un QR no requiere instalar ninguna app. |
| **Trazabilidad** | Cada incidencia queda registrada con fecha, lugar, usuario y estado. |
| **Transparencia** | Los reportantes pueden ver el estado real de su ticket. |
| **Valor académico** | Ideal para practicar State Machine Testing, RBAC y pipelines CI/CD. |
| **Backend as a Service** | Supabase elimina la gestión de servidores de base de datos en etapa temprana. |
| **Escalabilidad horizontal** | Laravel + Supabase permite escalar sin re-arquitectura inmediata. |

### ❌ Contras y Riesgos — Con Mitigaciones

#### 1. 🔗 Dependencia de Conectividad
> **Riesgo:** Si la red Wi-Fi del campus cae, el sistema queda inoperativo.

**Mitigación:** Implementar un *Service Worker* para soporte offline básico (PWA) que encole los tickets y los sincronice cuando se recupere la conexión.

#### 2. 🖨️ Gestión Física de los Códigos QR
> **Riesgo:** Los QR pueden dañarse, cubrirse con graffiti o ser reemplazados por QR fraudulentos.

**Mitigación:**
- Imprimir QR con laminado resistente.
- Firmar digitalmente cada QR (contienen un *token* único + hash de la ubicación verificado en el backend).
- Validar en servidor que el QR pertenece a un espacio registrado antes de crear el ticket.

#### 3. 🗑️ Tickets Spam / Duplicados
> **Riesgo:** Un mismo problema puede generar decenas de tickets idénticos desde cualquier vía (QR, API REST, formulario manual).

**Mitigación:**
- **`TicketDeduplicationService`** centralizado: toda ruta de creación (QR controller, API controller, Livewire form) invoca este servicio antes de insertar. Si existe un ticket *Abierto* o *En Proceso* para la misma ubicación **y categoría** en las últimas 24 h, redirige al ticket existente.
- **Índice UNIQUE parcial en DB** (última línea de defensa): `UNIQUE (location_id, category_id) WHERE state IN ('open', 'in_progress')`. Aunque la lógica de aplicación falle, la base de datos rechaza el duplicado.
- Rate limiting por IP (`throttle:10,1`) y por usuario autenticado (`throttle:5,1`) en todas las rutas de creación.
- La ventana de 24 h es configurable vía `config/tickets.php` (clave `dedup_window_hours`, valor por defecto: `24`). Crear `config/tickets.php` con `return ['dedup_window_hours' => env('DEDUP_WINDOW_HOURS', 24)];`.

#### 4. 🔐 Autenticación y Anonimato
> **Riesgo:** Sin login, cualquiera con el QR puede enumerar incidencias del edificio (riesgo de privacidad e ingeniería social).

**Mitigación:**
- **Crear** un ticket siempre requiere autenticación (OAuth con cuenta institucional Google/Microsoft).
- **Ver el estado** de un ticket vía QR también requiere login (se redirige al flujo OAuth antes de mostrar cualquier información). El escaneo QR sin login solo muestra una pantalla de bienvenida genérica sin revelar datos de incidencias.
- Los IDs de ticket internos (UUID) **no se exponen en URLs públicas**; se usa un `slug` opaco o el `qr_token` de la ubicación como referencia externa.
- Rate limiting: `throttle:20,1` en `/scan/{token}` (20 req/min por IP) y `throttle:5,1` en creación de tickets.
- Las políticas RLS de Supabase bloquean toda consulta sin JWT válido (`auth.role() = 'authenticated'`).

#### 5. 📊 Adopción por el Equipo de Mantenimiento
> **Riesgo:** Si mantenimiento no actualiza los estados, el sistema pierde credibilidad rápidamente.

**Mitigación:**
- Notificaciones push/email automáticas al crear un ticket.
- Dashboard simple con KPIs visibles para jefatura.
- SLA visible: tiempo promedio de resolución por categoría.

#### 6. 🧩 Complejidad del Stack para un Equipo Junior
> **Riesgo:** Laravel + Supabase + QR + RBAC + CI/CD puede ser demasiado para un equipo sin experiencia previa.

**Mitigación — Fases de implementación incrementales para 2 devs:**

| Fase | Objetivo | Componentes activos | Criterio de salida |
|---|---|---|---|
| **1 – Base** | Login funciona, se puede crear un ticket | Auth OAuth, modelo Ticket, formulario Livewire básico | `php artisan test` verde; ticket visible en DB |
| **2 – Estados** | Máquina de estados operativa, historial visible | `spatie/model-states`, `state_history`, notificación email | Transición open→in_progress funciona en UI |
| **3 – QR + Dedup** | Escanear QR pre-rellena el formulario; no crea duplicados | `QrScanController`, `TicketDeduplicationService`, índice UNIQUE parcial | Test `QrScanTest` verde; duplicado bloqueado |
| **4 – RBAC** | Roles aplicados; reporters no pueden cambiar estado | `spatie/permission`, `TicketPolicy`, RLS Supabase | Test `RbacTest` completo verde |
| **5 – CI/CD + Monitoring** | Pipeline verde, errores visibles en producción | GitHub Actions, Telescope (local), logs centralizados | PR bloqueado si lint/tests fallan |

- **Feature flags** con variable `.env`: `FEATURE_QR_ENABLED=false` desactiva el módulo QR sin romper nada.
- Cada fase tiene su propia rama `feature/fase-X` y su Pull Request para revisión mutua.
- **Una fase a la vez**: no pasar a la siguiente hasta que los tests de la actual estén en verde.

#### 7. 🔒 Seguridad del API Key de Supabase
> **Riesgo:** Exponer `SUPABASE_ANON_KEY` o `SUPABASE_SERVICE_ROLE_KEY` en un bundle público o en el repositorio permite acceso directo a la base de datos.

**Mitigación:**
- **`SUPABASE_ANON_KEY`** vive **solo en el backend Laravel** (archivo `.env`, nunca en código JS compilado ni en variables de entorno del frontend). Todo acceso a Supabase pasa por el backend que actúa como proxy.
- **`SUPABASE_SERVICE_ROLE_KEY`** se usa exclusivamente para tareas de sistema (seeds, migraciones, sync de roles). Se almacena en **GitHub Secrets** (CI/CD) o en el gestor de secretos del host de producción; jamás en `.env` de desarrollo compartido.
- **Rotación de claves:** cada 90 días (o inmediatamente si hay sospecha de compromiso) regenerar las claves en el panel de Supabase y actualizar los Secrets de GitHub y del servidor de producción.
- **Separación de entornos:**

| Entorno | Proyecto Supabase | `.env` usado | Rama Git |
|---|---|---|---|
| Local (dev) | `infra-tickets-dev` | `.env` (no commiteado) | `feature/*`, `develop` |
| Staging | `infra-tickets-staging` | GitHub Secret `ENV_STAGING` | `develop` |
| Producción | `infra-tickets-prod` | GitHub Secret `ENV_PROD` | `main` |

- **Nunca reutilizar** las claves de producción en local ni en staging.
- Toda la lógica de negocio pasa por el backend Laravel; Row Level Security (RLS) actúa como segunda línea de defensa.

#### 8. 📈 Escalabilidad de Supabase en Plan Gratuito
> **Riesgo:** El plan free de Supabase tiene límites de conexiones y almacenamiento.

**Mitigación:**
- Documentar los límites en el README.
- Preparar el proyecto para migrar a un plan pago o a PostgreSQL self-hosted con mínimos cambios (la capa ORM de Laravel abstrae esto).

---

## 🏗️ Decisiones de Arquitectura y Mejores Prácticas

### Tipo de Arquitectura de Software: Monolito Modular con Arquitectura en Capas

Para un proyecto académico con un equipo pequeño, **un monolito bien estructurado es superior a microservicios**. Los microservicios añaden complejidad operativa (orquestación de contenedores, comunicación entre servicios, consistencia eventual) sin beneficio real a esta escala. En cambio, un monolito modular combinado con una arquitectura en capas ofrece:

- **Simplicidad operativa:** Un solo despliegue, un solo repositorio, un solo pipeline CI/CD.
- **Refactoring sencillo:** Los cambios internos no requieren coordinación entre múltiples servicios.
- **Testing integrado:** Tests unitarios, de integración y E2E corren contra una sola aplicación.
- **Migración futura:** La separación en capas y módulos permite extraer servicios individuales si el proyecto escala significativamente.

> **¿Por qué no microservicios?** Un sistema de tickets con ~4 entidades principales (Ticket, Location, Category, User) y un equipo de 3-5 desarrolladores no justifica la sobrecarga de múltiples servicios, API gateways y orquestación distribuida. La regla de oro: *empieza con un monolito bien modularizado y extrae servicios solo cuando el crecimiento lo exija*.

> **¿Por qué no arquitectura hexagonal pura?** Aunque la separación Domain/Infrastructure ya sigue principios de hexagonal, la implementación estricta con puertos y adaptadores formales añade capas de abstracción innecesarias para el tamaño del equipo. La arquitectura en capas es más intuitiva para desarrolladores en formación y logra el mismo objetivo práctico de aislamiento.

### Patrón de Diseño Principal: State Machine

El ciclo de vida del ticket es el núcleo del sistema. Se implementa como una **State Machine explícita** usando la librería [`spatie/laravel-model-states`](https://github.com/spatie/laravel-model-states), lo que garantiza:

- Transiciones válidas controladas en un único lugar.
- Imposibilidad de pasar de *Resuelto* a *Abierto* directamente.
- Eventos disparados automáticamente en cada transición (para notificaciones, auditoría).
- Cobertura de tests de máquina de estados sencilla y exhaustiva.

### Arquitectura de Capas

```
┌─────────────────────────────────────────────────┐
│                Presentation Layer               │
│         Livewire Components + Blade Templates   │
├─────────────────────────────────────────────────┤
│                Application Layer                │
│        Controllers + Form Requests + Jobs       │
├─────────────────────────────────────────────────┤
│                  Domain Layer                   │
│ Models + State Machine + Policies + Repositories│
│ + IA Services (Embeddings, Deduplication)       │
├─────────────────────────────────────────────────┤
│              Infrastructure Layer               │
│      Supabase (PostgreSQL) + Storage + Auth     │
└─────────────────────────────────────────────────┘
```

### Diagrama de Flujo: Creación de Ticket con IA
```
┌──────────────────────────────────────────────────┐
│    Nuevo Ticket Creado (Descripción + Ubicación) │
└───────────────────┬──────────────────────────────┘
                    │
                    ▼
┌─────────────────────────────────────────────────┐
│  Hugging Face: all-MiniLM-L6-v2 (Embedding)     │
│  • Convierte descripción a vector (384 dims)    │
│  • Procesa en ~100-200ms                        │
└───────────────────┬─────────────────────────────┘
                    │
                    ▼
┌──────────────────────────────────────────────────┐
│  Búsqueda en ticket_embeddings                   │
│  • Filtra por ubicación + categoría              │
│  • Busca tickets abiertos/in_progress            │
│  • Calcula similitud coseno vs cada uno          │
└───────────────────┬──────────────────────────────┘
                    │
                    ▼
        ┌───────────┴───────────┐
        │                       │
SÍ: Similitud ≥ 70%    NO: Similitud < 70%
        │                       │
        ▼                       ▼
┌───────────────────┐   ┌──────────────────┐
│ DUPLICADO DETECTADO   │  TICKET NUEVO    │
│ • Se marca        │   │ • Se crea        │
│ • Se vincula      │   │ • Se almacena    │
│ • Se audita (IA)  │   │ • Se audita (IA) │
└───────────────────┘   └──────────────────┘
```
### Flujo de Implementación
### Fase 1: Detección Básica (ACTUAL)

1. Usuario crea ticket con descripción
2. Laravel dispara evento (ticket.created)
3. Job asíncrono llama a Hugging Face API
4. Se genera embedding y se almacena
5. Se buscan matches en últimas 24h
6. Si similitud ≥ 0.70 → Se marca como posible duplicado
7. Administrador revisa antes de consolidar

---

## 🎨 Patrones de Diseño Aplicados

La selección de patrones de diseño se realizó con base en un **análisis crítico** de las necesidades reales del sistema, priorizando patrones que resuelven problemas concretos del dominio y evitando la sobre-ingeniería. Cada patrón seleccionado tiene una justificación directa vinculada a un requisito funcional o no funcional del sistema de ticketing.

### Criterios de Selección

1. **Necesidad real** — ¿El patrón resuelve un problema que existe en el sistema?
2. **Complejidad proporcional** — ¿La complejidad añadida se justifica por el beneficio obtenido?
3. **Familiaridad del equipo** — ¿El equipo (junior/académico) puede entenderlo e implementarlo correctamente?
4. **Soporte del framework** — ¿Laravel ya ofrece infraestructura para implementar el patrón de forma idiomática?

### Patrones de Comportamiento (Behavioral)

| Patrón | Aplicación en el Sistema | Justificación |
|---|---|---|
| **State** | Gestión del ciclo de vida del ticket (`Open → InProgress → Resolved / Rejected`) mediante `spatie/laravel-model-states`. | El ticket es una máquina de estados con transiciones controladas. Sin este patrón, la lógica de estados se dispersaría en `if/else` por todo el código, haciendo los tests de transiciones extremadamente frágiles. |
| **Observer** | Eventos Laravel (`TicketCreated`, `TicketStateChanged`) que disparan Listeners para notificaciones por email, registro en `state_history` y actualización del dashboard. | Desacopla la lógica de negocio (cambiar estado) de los efectos secundarios (notificar, auditar). Permite agregar nuevos Listeners sin modificar el código de transición. |
| **Strategy** | Políticas de autorización (`TicketPolicy`) actúan como estrategias intercambiables de autorización según el rol del usuario (reporter, maintenance, admin, super_admin). | Cada rol tiene reglas de autorización distintas. El patrón Strategy permite que Laravel evalúe la política correcta sin `switch/case` en los controladores, cumpliendo con el Open/Closed Principle. |
| **Chain of Responsibility** | Pipeline de Middleware de Laravel: `auth`, `verified`, `throttle`, `role:admin` se encadenan para procesar cada request HTTP. | Cada middleware decide si pasa la petición al siguiente o la rechaza. Permite agregar capas de seguridad (rate limiting, CORS, validación de QR) de forma modular y reutilizable. |
| **Template Method** | Clases de Notificación (`TicketCreated`, `TicketStateChanged`) definen una estructura común (`via()`, `toMail()`, `toArray()`) con implementaciones específicas para cada tipo de notificación. | Garantiza consistencia en la estructura de todas las notificaciones mientras permite personalizar el contenido de cada una. |

### Patrones Creacionales (Creational)

| Patrón | Aplicación en el Sistema | Justificación |
|---|---|---|
| **Factory Method** | Model Factories de Laravel (`Ticket::factory()`, `User::factory()->withRole('maintenance')`) para generación de datos de prueba. `QrCodeService` actúa como factory para generar tokens y códigos QR firmados. | Centraliza la lógica de creación de objetos complejos. Los factories de testing permiten crear escenarios reproducibles, y el `QrCodeService` encapsula la generación de tokens HMAC-SHA256 en un solo punto. |
| **Singleton** | El Service Container de Laravel registra `QrCodeService` y `TicketDeduplicationService` como instancias únicas (singletons) compartidas durante el ciclo de vida del request. | Evita instanciaciones redundantes de servicios que mantienen estado o configuración costosa. Laravel gestiona esto de forma transparente a través de su contenedor de inyección de dependencias. |

### Patrones Estructurales (Structural)

| Patrón | Aplicación en el Sistema | Justificación |
|---|---|---|
| **Facade** | Facades de Laravel (`Auth`, `Cache`, `Storage`, `Notification`) proporcionan una interfaz simplificada a subsistemas complejos (Supabase Auth, Redis, Supabase Storage). | Reduce el acoplamiento entre controladores y servicios internos. Los controladores acceden a `Storage::put()` sin conocer la implementación de Supabase Storage, facilitando futuras migraciones. |
| **Adapter** | La capa de Infraestructura adapta los servicios de Supabase (Auth, Storage, PostgreSQL) a las interfaces esperadas por Laravel (Eloquent ORM, Filesystem, Auth Guard). | Permite usar Supabase como backend sin acoplar la lógica de negocio a su API específica. Si en el futuro se migra a PostgreSQL self-hosted o AWS S3, solo cambian los adapters, no los modelos ni los controladores. |
| **Composite** | Jerarquía de componentes Livewire y Blade: `Dashboard` compone `TicketList`, que a su vez compone elementos individuales de ticket. Componentes reutilizables (`<x-ticket-card>`, `<x-state-badge>`) se combinan en vistas más complejas. | Permite construir interfaces complejas a partir de componentes simples y reutilizables, manteniendo cada componente con una responsabilidad única y facilitando el testing individual de cada uno. |

### Patrones Descartados (con Justificación)

No todos los patrones de diseño son adecuados para este sistema. Los siguientes fueron evaluados y descartados intencionalmente:

| Patrón | Motivo de Descarte |
|---|---|
| **Abstract Factory** | El sistema solo crea un tipo de ticket y un tipo de QR. No existen familias de objetos relacionados que justifiquen este nivel de abstracción. |
| **Builder** | Los tickets tienen un constructor relativamente simple (título, descripción, ubicación, categoría). El `FormRequest` de Laravel ya valida y estructura los datos de entrada de forma suficiente. |
| **Prototype** | No existe necesidad de clonar tickets ni objetos complejos. La deduplicación redirige al ticket existente en lugar de clonarlo. |
| **Flyweight** | No hay objetos repetitivos con alto consumo de memoria que justifiquen compartir estado intrínseco. |
| **Mediator** | La comunicación entre componentes Livewire es directa y simple. No hay suficientes componentes interdependientes como para justificar un mediador centralizado. |
| **Visitor** | La estructura de datos (tickets, ubicaciones, categorías) no requiere operaciones polimórficas sobre una jerarquía de objetos heterogéneos. |
| **Memento** | El historial de estados se implementa con la tabla `state_history` (log append-only), que es más simple y auditable que el patrón Memento clásico para restaurar estados previos. |

### Diagrama de Patrones en la Arquitectura

```
┌─────────────────────────────────────────────────────────────┐
│                    Presentation Layer                        │
│  [Composite] Livewire Components + Blade Templates          │
│  [Template Method] Notification Templates                   │
├─────────────────────────────────────────────────────────────┤
│                    Application Layer                         │
│  [Chain of Responsibility] Middleware Pipeline               │
│  [Strategy] TicketPolicy (RBAC Authorization)               │
│  [Factory Method] Form Requests + Validation                │
├─────────────────────────────────────────────────────────────┤
│                      Domain Layer                            │
│  [State] TicketState Machine (Open/InProgress/Resolved)     │
│  [Observer] Events & Listeners (Notifications, Audit)       │
│  [Singleton] QrCodeService, DeduplicationService            │
├─────────────────────────────────────────────────────────────┤
│                   Infrastructure Layer                       │
│  [Adapter] Supabase ↔ Laravel (Auth, Storage, DB)           │
│  [Facade] Simplified access to Auth, Cache, Storage         │
└─────────────────────────────────────────────────────────────┘
```

---

## 🛠️ Stack Tecnológico

### Backend
| Tecnología | Versión | Propósito |
|---|---|---|
| **PHP** | 8.2.12 (CLI, ZTS Visual C++ 2019 x64) | Runtime backend |
| **Laravel Framework** | 12.56.0 | Framework backend |
| **Composer** | 2.9.5 | Gestión de dependencias PHP |
| **Livewire** | 4.2.4 | Componentes reactivos sin JS pesado |
| **spatie/laravel-model-states** | 2.12.1 | Máquina de estados para tickets |
| **spatie/laravel-permission** | 6.25.0 | RBAC (Roles y Permisos) |
| **simplesoftwareio/simple-qrcode** | 4.2.0 | Generación de códigos QR |
| **Laravel Sanctum** | 4.3.1 | Autenticación de API tokens |

### Base de Datos y Auth
| Tecnología | Propósito |
|---|---|
| **Supabase** | PostgreSQL gestionado + Auth + Storage |
| **Supabase Auth** | Autenticación OAuth (Google, Microsoft) |
| **Supabase Storage** | Almacenamiento de imágenes adjuntas a tickets |
| **Row Level Security** | Seguridad a nivel de fila en PostgreSQL |

### Frontend
| Tecnología | Versión | Propósito |
|---|---|---|
| **Node.js** | v22.20.0 | Runtime frontend |
| **npm** | 10.9.3 | Gestión de paquetes JS |
| **Tailwind CSS** | 3.x | Framework de estilos utilitarios |
| **Alpine.js** | 3.x | Interactividad JS ligera |
| **Heroicons** | - | Iconografía |

### DevOps y Calidad
| Tecnología | Propósito |
|---|---|
| **GitHub Actions** | Pipeline CI/CD |
| **PHPUnit / Pest** | Testing unitario e integración |
| **Laravel Dusk** | Testing E2E (browser) |
| **PHP-CS-Fixer** | Formateo de código |
| **PHPStan (Larastan)** | Análisis estático |
| **Docker Compose** | Entorno containerizado local y despliegues |

---

## 📦 Versiones de Dependencias y Entorno

### 1) System Requirements

- **PHP**: 8.2.12 o superior compatible con extensiones listadas
- **Laravel Framework**: 12.56.0
- **Composer**: 2.9.5
- **Node.js**: v22.20.0
- **npm**: 10.9.3
- **Docker**: 29.3.1 (build c2be9cc) *(opcional para entorno containerizado)*
- **PostgreSQL**: 13+ (gestionado en Supabase)

### 2) Technology Stack

| Capa | Tecnología | Versión |
|---|---|---|
| Backend | PHP | 8.2.12 |
| Backend | Laravel Framework | 12.56.0 |
| Backend | Livewire | 4.2.4 |
| Backend | Laravel Sanctum | 4.3.1 |
| Backend | spatie/laravel-permission | 6.25.0 |
| Backend | spatie/laravel-model-states | 2.12.1 |
| Backend | simplesoftwareio/simple-qrcode | 4.2.0 |
| Frontend | Node.js | v22.20.0 |
| Frontend | npm | 10.9.3 |
| Base de Datos | PostgreSQL (Supabase) | 13+ |
| Containerización | Docker | 29.3.1 |

### 3) Development Environment

Configuración local recomendada:

```bash
git clone https://github.com/Nik-920/infra-ticketing-system.git
cd infra-ticketing-system/composer
cp .env.example .env
composer install
npm install
php artisan key:generate
php artisan migrate
npm run dev
php artisan serve
```

### 4) Docker & Containerization

- **Docker Engine**: 29.3.1 (build c2be9cc)
- **Docker Compose** como orquestador principal (sin Laravel Sail):

```bash
cp .env.example .env
# Completar credenciales de Supabase en .env
docker compose build
docker compose up -d
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate
```

Servicios incluidos en Compose:

- `app` (PHP-FPM Laravel)
- `nginx` (servidor web)
- `redis` (cache/sesiones/colas)
- `queue` (worker de jobs)

### 5) PHP Extensions

Extensiones críticas habilitadas:

- ✅ GD (Bundled 2.1.0 compatible) — generación de QR
- ✅ PDO PostgreSQL (`pdo_pgsql`)
- ✅ PostgreSQL (`pgsql v11.4`)
- ✅ OpenSSL 3.0.11
- ✅ cURL 8.4.0
- ✅ mbstring
- ✅ BCMath
- ✅ JSON
- ✅ ZIP

### 6) Composer Dependencies

Paquetes Laravel principales instalados:

- `livewire/livewire`: **4.2.4** — componentes reactivos
- `spatie/laravel-permission`: **6.25.0** — RBAC
- `spatie/laravel-model-states`: **2.12.1** — state machine
- `laravel/sanctum`: **4.3.1** — autenticación API
- `simplesoftwareio/simple-qrcode`: **4.2.0** — generación de QR

### 7) AI Integration

- **Proveedor**: Hugging Face Inference API
- **Uso**: embeddings semánticos y deduplicación inteligente
- **Modelos configurados**:
    - `sentence-transformers/all-MiniLM-L6-v2` (embeddings)
    - `facebook/bart-large-mnli` (clasificación zero-shot)

Variables de entorno asociadas:

```dotenv
HUGGINGFACE_ENABLED=true
HUGGINGFACE_API_KEY=hf_xxxxxxxxxxxxxxxxxxxx
HUGGINGFACE_EMBEDDING_MODEL=sentence-transformers/all-MiniLM-L6-v2
HUGGINGFACE_CLASSIFICATION_MODEL=facebook/bart-large-mnli
```

### 8) Database Schema

Motor y esquema actual:

- **Motor**: PostgreSQL 13+ sobre Supabase
- **Tablas base**: `users`, `tickets`, `locations`, `categories`, `state_history`
- **Tablas nuevas IA/histórico**: `ticket_media`, `ticket_embeddings`, `location_incident_history`, `ticket_ai_logs`
- **RBAC**: tablas de roles y permisos

---

## ✨ Características Principales

- 📱 **Reporte por QR** — Escanear el código del aula abre el formulario pre-rellenado con la ubicación.
- 🔄 **Máquina de Estados** — Flujo controlado: `Abierto → En Proceso → Resuelto / Rechazado`.
- 🔒 **RBAC** — Roles: `super_admin`, `admin`, `maintenance`, `reporter`.
- 📧 **Notificaciones** — Email + push en cada cambio de estado.
- 🖼️ **Adjuntos** — Fotos del fallo subidas a Supabase Storage.
- 📊 **Dashboard** — KPIs: tickets abiertos, tiempo medio de resolución, top incidencias.
- 🔄 **Deduplicación** — Previene tickets duplicados para el mismo problema.
- 🕵️ **Auditoría** — Log completo de cambios de estado con usuario y timestamp.
- 🌐 **API REST** — Endpoints para integración con otros sistemas.
- 🐳 **Docker Ready** — Entorno reproducible con Docker Compose.

---

## 🏛️ Arquitectura del Sistema

```
                          ┌─────────────────────────┐
                          │     Usuario / Alumno     │
                          │   (Escanea QR con móvil) │
                          └────────────┬────────────┘
                                       │ HTTPS
                          ┌────────────▼────────────┐
                          │    Laravel Application  │
                          │  ┌──────────────────┐   │
                          │  │   Controllers    │   │
                          │  │   Livewire       │   │
                          │  │   Jobs / Events  │   │
                          │  └────────┬─────────┘   │
                          │           │             │
                          │  ┌────────▼─────────┐   │
                          │  │   Domain Models  │   │
                          │  │   State Machine  │   │
                          │  │   Policies/RBAC  │   │
                          │  └────────┬─────────┘   │
                          └───────────┼─────────────┘
                                      │
                   ┌──────────────────┼──────────────────┐
                   │                  │                  │
        ┌──────────▼──────┐  ┌────────▼────────┐  ┌─────▼──────────┐
        │  Supabase Auth  │  │Supabase Postgres │  │Supabase Storage│
        │  (OAuth / JWT)  │  │ (RLS Policies)  │  │ (Ticket Photos)│
        └─────────────────┘  └─────────────────┘  └────────────────┘
```

---

## 🔄 Máquina de Estados de Tickets

### Diagrama de Transiciones

```
                    ┌─────────────┐
                    │    OPEN     │◄──────────────────────┐
                    │  (Abierto)  │                       │
                    └──────┬──────┘                       │
                           │                              │
                  [Admin / Maintenance                    │
                   asigna el ticket]                      │
                           │                              │
                    ┌──────▼──────┐            [Re-apertura por
                    │ IN_PROGRESS │             Admin si necesario]
                    │ (En Proceso)│                       │
                    └──────┬──────┘                       │
                           │                              │
              ┌────────────┴────────────┐                 │
              │                         │                 │
     [Trabajo completado]    [No procede / Duplicado]     │
              │                         │                 │
       ┌──────▼──────┐         ┌────────▼────────┐       │
       │  RESOLVED   │         │    REJECTED     │       │
       │  (Resuelto) │         │   (Rechazado)   │       │
       └─────────────┘         └────────┬────────┘       │
                                        │                 │
                                [Admin puede reabrir]─────┘
```

### Transiciones Permitidas

| Desde → Hacia | Quién puede ejecutarla | Condición |
|---|---|---|
| `open` → `in_progress` | `maintenance`, `admin`, `super_admin` | Ticket debe tener descripción completa |
| `in_progress` → `resolved` | `maintenance`, `admin`, `super_admin` | Debe incluir comentario de cierre |
| `in_progress` → `rejected` | `admin`, `super_admin` | Debe incluir motivo de rechazo |
| `rejected` → `open` | `admin`, `super_admin` | Re-apertura justificada |
| `resolved` → `open` | `super_admin` | Solo en caso excepcional |

---

## 👥 Control de Acceso Basado en Roles (RBAC)

### Matriz de Permisos

| Acción | `reporter` | `maintenance` | `admin` | `super_admin` |
|---|:---:|:---:|:---:|:---:|
| Ver tickets propios | ✅ | ✅ | ✅ | ✅ |
| Ver todos los tickets | ❌ | ✅ | ✅ | ✅ |
| Crear ticket | ✅ | ✅ | ✅ | ✅ |
| Editar ticket propio | ✅ | ❌ | ✅ | ✅ |
| Cambiar estado `open → in_progress` | ❌ | ✅ | ✅ | ✅ |
| Cambiar estado `in_progress → resolved` | ❌ | ✅ | ✅ | ✅ |
| Rechazar ticket | ❌ | ❌ | ✅ | ✅ |
| Reabrir ticket rechazado | ❌ | ❌ | ✅ | ✅ |
| Gestionar ubicaciones/QR | ❌ | ❌ | ✅ | ✅ |
| Gestionar usuarios y roles | ❌ | ❌ | ❌ | ✅ |
| Ver dashboard completo | ❌ | ❌ | ✅ | ✅ |
| Exportar reportes | ❌ | ❌ | ✅ | ✅ |


### Estrategia única de autenticación y sincronización de roles (lista corta para 2 devs junior)

1) **Identidad = Supabase Auth (OAuth/JWT).** Laravel **no usa Sanctum para sesiones de usuario**; Sanctum queda solo para tokens técnicos (CI/webhooks) y tiene su propia tabla aislada.
2) **Fuente de verdad de roles/permisos = tablas de `spatie/laravel-permission`.** Cada alta/cambio de rol se guarda allí.
3) **Claim `app_role` en el JWT:** después de asignar un rol en Laravel, se invoca el admin API de Supabase (`auth.admin.updateUserById`) con la `service_role` para escribir `app_metadata.app_role` con el rol principal (`reporter | maintenance | admin | super_admin`). Supabase incluye ese claim automáticamente en el próximo login/refresh.
4) **Sincronización mínima viable:** crear un comando/endpoint interno sencillo (ej.: `php artisan app:sync-supabase-roles`) que:
    - Lee `users` + `model_has_roles`, toma el rol más alto.
    - Actualiza `app_metadata.app_role` en Supabase.
    - Reintenta a lo sumo 3 veces y registra fallos.
5) **Login flow:** OAuth → Supabase entrega JWT con `app_role` → Laravel valida el JWT (no crea sesión Sanctum) → políticas/permissions de Laravel y RLS de Supabase usan el mismo claim.
6) **Roles de ejemplo listos para probar:** crear usuario con OAuth, asignar rol en Laravel, ejecutar `app:sync-supabase-roles`, volver a hacer login: el JWT ya trae `app_role` y desbloquea RLS.

---

## 📱 Sistema de Códigos QR

### Flujo de Generación y Validación

```bash
Admin crea ubicación
        │
        ▼
Sistema genera qr_token único (HMAC-SHA256)
        │
        ▼
Se genera imagen QR → URL firmada:
https://app.example.com/scan/{qr_token}
        │
        ▼
QR se imprime y coloca físicamente en el aula
        │
        ▼
Usuario escanea QR con móvil
        │
        ▼
Backend valida:
  1. ¿qr_token existe en DB?
  2. ¿location.is_active = true?
  3. ¿Existe ticket duplicado activo?
        │
    ┌───┴──────────┐
    ▼               ▼
Redirige al    Muestra formulario
ticket activo  pre-rellenado con
               la ubicación
```

## 📁 Estructura del Proyecto

```bash
├── composer/                               # Aplicación Laravel
│   ├── app/
│   │   ├── Http/
│   │   │   ├── Controllers/
│   │   │   │   ├── Api/                    # Controladores API (REST/Sanctum)
│   │   │   │   └── Web/                    # Controladores Web (Blade/Livewire)
│   │   │   └── Middleware/
│   │   │       └── RateLimitHuggingFace.php
│   │   ├── Models/
│   │   │   ├── Ticket.php
│   │   │   ├── TicketEmbedding.php
│   │   │   ├── LocationIncidentHistory.php
│   │   │   ├── TicketAiLog.php
│   │   │   ├── Category.php
│   │   │   ├── Location.php
│   │   │   ├── User.php
│   │   │   ├── StateHistory.php
│   │   │   └── TicketMedia.php
│   │   ├── Providers/
│   │   │   └── AppServiceProvider.php
│   │   │   └── EventServiceProvider.php
│   │   ├── Services/
│   │   │   └── Ai/
│   │   │       ├── HuggingFaceService.php
│   │   │       ├── EmbeddingService.php
│   │   │       └── DeduplicationService.php
│   │   ├── Jobs/
│   │   │   ├── GenerateTicketEmbedding.php
│   │   │   └── DetectDuplicates.php
│   │   │   └── UpdateRecurrenceHistory.php
│   │   │   └── LogAiDecision.php
│   │   │   └── WriteAiAuditLog.php
│   │   ├── Events/
│   │   │   ├── TicketCreated.php
│   │   │   └── DuplicateDetected.php
│   │   │   └── TicketResolved.php (filtrar cuando llega a resolved)
│   │   ├── Listeners/
│   │   │   ├── GenerateEmbeddingOnTicketCreated.php
│   │   │   └── NotifyDuplicateDetected.php
│   │   │   └── UpdateRecurrenceOnTicketResolved.php
│   │   │   └── DetectDuplicatesOnEmbeddingReady.php 
│   │   ├── Repositories/
│   │   │   ├── TicketRepository.php
│   │   │   └── EmbeddingRepository.php
│   │   ├── Exceptions/
│   │   │   └── HuggingFaceException.php
│   │   ├── Policies/
│   │   │   ├── TicketEmbeddingPolicy.php
│   │   │   └── TicketAiLogPolicy.php
│   │   ├── Notifications/
│   │   │   ├── DuplicateTicketDetected.php
│   │   │   └── RecurrenceAlert.php
│   │   └── Livewire/
│   │       ├── TicketDuplicates.php
│   │       └── RecurrenceAlert.php
│   ├── config/
│   │   └── ai.php                          # Configuración Hugging Face y umbrales
│   ├── database/
│   │   ├── migrations/                     # Incluye tablas ticket_embeddings, location_incident_histories, ticket_ai_logs
│   │   └── seeders/                        # Seeders con ejemplos para escenarios IA
│   ├── resources/
│   │   ├── views/
│   │   │   ├── duplicates/
│   │   │   ├── ai/
│   │   │   └── tickets/
│   │   └── js/                             # Notificaciones y actualización de UI IA
│   └── tests/
│       ├── Unit/Services/                  # Tests unitarios de servicios IA
│       └── Feature/Ai/                     # Tests funcionales de deduplicación/recurrencia
├── Dockerfile                              # Imagen PHP-FPM multistage
├── docker-compose.yml                      # Orquestación de servicios
├── docker/                                 # Scripts auxiliares (entrypoint)
├── supabase/
│   └── migrations/                         # Migraciones SQL para Supabase
├── docs/                                   # Documentación técnica y funcional adicional
└── .github/
    └── workflows/
        ├── ci.yml
        └── ai-tests.yml                    # Pipeline CI específico para IA
```

## ⚙️ Pipeline CI/CD

### GitHub Actions — CI (ajustado a Supabase y RLS)

Flujo simple para 2 devs junior:
1. **Lint** (composer + pint + phpstan).
2. **Tests** con base Supabase (imagen oficial con extensiones `auth.*` y `pgcrypto`) para que la migración que referencia `auth.users` no falle.
3. **Smoke RLS**: genera un JWT con `app_role` y hace una consulta mínima contra PostgREST para verificar que la política responde (sin cubrir todo el sistema).

## 📊 Monitoreo y Observabilidad

Sin observabilidad, los problemas en producción tardan en detectarse y la credibilidad del sistema cae. A continuación se describe una estrategia mínima viable adaptada al stack y al tamaño del equipo.

### Logging

| Entorno | Herramienta | Configuración |
|---|---|---|
| **Local** | [Laravel Telescope](https://laravel.com/docs/telescope) | `composer require laravel/telescope --dev` + `php artisan telescope:install` |
| **Staging / Producción** | Canal `stack` de Laravel (stdout → servicio de logs) | `LOG_CHANNEL=stack` + `LOG_STACK=daily,stderr` |
| **Errores no controlados** | Integrar [Sentry](https://sentry.io) o similar | `composer require sentry/sentry-laravel` + `SENTRY_LARAVEL_DSN=...` |



---

## 🤝 Guía de Contribución

### Flujo de Trabajo Git

```
main ──────────────────────────────────────────►
 └── develop ─────────────────────────────────►
       ├── feature/ticket-state-machine ──► PR
       ├── feature/qr-scan-flow ──────────► PR
       └── fix/duplicate-ticket-bug ──────► PR
```

1. **Fork** del repositorio o crear rama desde `develop`.
2. Crear rama descriptiva: `feature/nombre-feature` o `fix/descripcion-bug`.
3. Escribir tests **antes** del código (TDD).
4. Asegurarse de que `php artisan test` y `vendor/bin/pint` pasen sin errores.
5. Crear Pull Request hacia `develop` con descripción completa.

### Convenciones de Código

- **PSR-12** como estándar de código PHP.
- **Commits convencionales**: `feat:`, `fix:`, `test:`, `docs:`, `refactor:`.
- Nombres de clases en **PascalCase**, métodos en **camelCase**.
- Tests en **snake_case** descriptivo: `it_creates_ticket_from_valid_qr_scan`.

---

## 📊 KPIs del Dashboard

El panel de administración muestra en tiempo real (Livewire polling cada 30s):

- 🔢 **Total de tickets** por estado (Abierto / En Proceso / Resuelto / Rechazado)
- ⏱️ **Tiempo medio de resolución** (por categoría y por edificio)
- 🏆 **Top 5 ubicaciones** con más incidencias
- 📈 **Evolución temporal** de tickets (últimos 30 días)
- 🚨 **Tickets críticos** sin atender en más de 48h
- 👤 **Ranking de reportantes** activos

---

## 📝 Licencia

Este proyecto está bajo la **Licencia MIT**. Ver el archivo [LICENSE](LICENSE) para más detalles.

---

<div align="center">

**Desarrollado con ❤️ para los cursos de DevOps, Testing y QA**

*Universidad · Desarrollo de Aplicaciones DevOps · Testing y Aseguramiento de la Calidad*

[![GitHub](https://img.shields.io/badge/GitHub-Nik--920-181717?style=flat-square&logo=github)](https://github.com/Nik-920)

</div>
