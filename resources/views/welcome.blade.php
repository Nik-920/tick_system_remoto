<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sistema de Reporte de Incidencias</title>
    <meta name="description" content="Plataforma centralizada para reportar, asignar y resolver incidencias operativas en tiempo real.">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700,800|dm-mono:400,500" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="welcome-body">

    <!-- ═══════════════════════════════ NAVBAR ═══════════════════════════════ -->
    <header class="welcome-nav" role="banner">
        <div class="welcome-nav-inner">
            <!-- Logo / Brand -->
            <a href="{{ url('/') }}" class="welcome-brand" aria-label="Inicio — Sistema de Incidencias">
                <span class="welcome-brand-icon" aria-hidden="true">
                    <svg width="28" height="28" viewBox="0 0 28 28" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <rect width="28" height="28" rx="7" fill="#1e40af"/>
                        <path d="M14 6.5L14 10M14 18v3.5M6.5 14H10M18 14h3.5" stroke="#93c5fd" stroke-width="2" stroke-linecap="round"/>
                        <circle cx="14" cy="14" r="3.5" fill="#3b82f6" stroke="#bfdbfe" stroke-width="1.5"/>
                        <circle cx="14" cy="14" r="1.2" fill="#fff"/>
                    </svg>
                </span>
                <span class="welcome-brand-name">
                    <span class="welcome-brand-primary">Incidencias</span>
                    <span class="welcome-brand-suffix">&nbsp;OPS</span>
                </span>
            </a>

            <!-- Auth Links -->
            <nav class="welcome-nav-links" aria-label="Autenticación">
                @if (Route::has('login'))
                    @auth
                        <a href="{{ route('dashboard.index') }}" class="welcome-nav-btn welcome-nav-btn--ghost">
                            Panel de control
                        </a>
                    @else
                        <a href="{{ route('login') }}" class="welcome-nav-btn welcome-nav-btn--ghost">
                            Iniciar sesión
                        </a>
                        @if (Route::has('register'))
                            <a href="{{ route('register') }}" class="welcome-nav-btn welcome-nav-btn--primary">
                                Registrarse
                            </a>
                        @endif
                    @endauth
                @endif
            </nav>
        </div>
    </header>

    <main>
        <!-- ═══════════════════════════════ HERO ═══════════════════════════════ -->
        <section class="welcome-hero" aria-labelledby="hero-heading">
            <!-- Background grid decoration -->
            <div class="welcome-hero-grid" aria-hidden="true"></div>
            <!-- Glow blobs -->
            <div class="welcome-blob welcome-blob--blue" aria-hidden="true"></div>
            <div class="welcome-blob welcome-blob--indigo" aria-hidden="true"></div>

            <div class="welcome-hero-inner">
                <!-- Status badge -->
                <div class="welcome-status-badge" role="status" aria-label="Estado del sistema operativo">
                    <span class="welcome-status-dot" aria-hidden="true"></span>
                    Sistema operativo · Todos los servicios activos
                </div>

                <h1 id="hero-heading" class="welcome-hero-title">
                    Incidencias bajo<br>
                    <span class="welcome-hero-title--accent">control total</span>
                </h1>

                <p class="welcome-hero-desc">
                    Plataforma centralizada para reportar, asignar y resolver incidencias operativas.
                    Trazabilidad completa, respuesta rápida y continuidad garantizada.
                </p>

                <div class="welcome-hero-actions">
                    @auth
                        <a href="{{ route('dashboard.index') }}" class="welcome-cta welcome-cta--primary">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/>
                                <rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>
                            </svg>
                            Ir al panel
                        </a>
                    @else
                        @if (Route::has('register'))
                            <a href="{{ route('register') }}" class="welcome-cta welcome-cta--primary">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M12 5v14M5 12h14"/>
                                </svg>
                                Crear cuenta
                            </a>
                        @endif
                        @if (Route::has('login'))
                            <a href="{{ route('login') }}" class="welcome-cta welcome-cta--ghost">
                                Iniciar sesión
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M5 12h14M13 6l6 6-6 6"/>
                                </svg>
                            </a>
                        @endif
                    @endauth
                </div>

                <!-- KPI strip -->
                <dl class="welcome-kpi-strip" aria-label="Métricas clave del sistema">
                    <div class="welcome-kpi-item">
                        <dt class="welcome-kpi-label">Tiempo de respuesta</dt>
                        <dd class="welcome-kpi-value">&lt; 5 min</dd>
                    </div>
                    <div class="welcome-kpi-sep" aria-hidden="true"></div>
                    <div class="welcome-kpi-item">
                        <dt class="welcome-kpi-label">Trazabilidad</dt>
                        <dd class="welcome-kpi-value">100%</dd>
                    </div>
                    <div class="welcome-kpi-sep" aria-hidden="true"></div>
                    <div class="welcome-kpi-item">
                        <dt class="welcome-kpi-label">Disponibilidad</dt>
                        <dd class="welcome-kpi-value">24 / 7</dd>
                    </div>
                    <div class="welcome-kpi-sep" aria-hidden="true"></div>
                    <div class="welcome-kpi-item">
                        <dt class="welcome-kpi-label">Escaneo QR</dt>
                        <dd class="welcome-kpi-value">Instantáneo</dd>
                    </div>
                </dl>
            </div>
        </section>

        <!-- ═══════════════════════════════ FLOW ═══════════════════════════════ -->
        <section class="welcome-flow" aria-labelledby="flow-heading">
            <div class="welcome-section-inner">
                <header class="welcome-section-header">
                    <p class="welcome-section-overline">Flujo de trabajo</p>
                    <h2 id="flow-heading" class="welcome-section-title">De la incidencia a la resolución</h2>
                    <p class="welcome-section-desc">Cuatro pasos claros que garantizan una gestión eficiente y trazable de cada incidente en tu organización.</p>
                </header>

                <ol class="welcome-flow-steps" aria-label="Pasos del flujo de incidencias">
                    <li class="welcome-flow-step">
                        <div class="welcome-flow-step-num" aria-hidden="true">01</div>
                        <div class="welcome-flow-step-icon" aria-hidden="true">
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M12 2a9 9 0 0 1 9 9 9 9 0 0 1-9 9 9 9 0 0 1-9-9 9 9 0 0 1 9-9z"/>
                                <path d="M12 8v4M12 16h.01"/>
                            </svg>
                        </div>
                        <h3 class="welcome-flow-step-title">Reportar</h3>
                        <p class="welcome-flow-step-desc">El usuario escanea el código QR de la ubicación o accede al sistema y registra el incidente con categoría, descripción y evidencia fotográfica.</p>
                    </li>

                    <li class="welcome-flow-step">
                        <div class="welcome-flow-step-num" aria-hidden="true">02</div>
                        <div class="welcome-flow-step-icon" aria-hidden="true">
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="8" r="4"/>
                                <path d="M6 20v-1a6 6 0 0 1 12 0v1"/>
                                <path d="M19 11l2 2-5 5-3-3"/>
                            </svg>
                        </div>
                        <h3 class="welcome-flow-step-title">Asignar</h3>
                        <p class="welcome-flow-step-desc">El equipo administrativo revisa el ticket, lo clasifica por prioridad y lo asigna al técnico o área responsable de atender el incidente.</p>
                    </li>

                    <li class="welcome-flow-step">
                        <div class="welcome-flow-step-num" aria-hidden="true">03</div>
                        <div class="welcome-flow-step-icon" aria-hidden="true">
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/>
                            </svg>
                        </div>
                        <h3 class="welcome-flow-step-title">Resolver</h3>
                        <p class="welcome-flow-step-desc">El técnico atiende el incidente en campo, actualiza el estado del ticket y documenta las acciones correctivas realizadas para el cierre.</p>
                    </li>

                    <li class="welcome-flow-step">
                        <div class="welcome-flow-step-num" aria-hidden="true">04</div>
                        <div class="welcome-flow-step-icon" aria-hidden="true">
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M22 12h-4l-3 9L9 3l-3 9H2"/>
                            </svg>
                        </div>
                        <h3 class="welcome-flow-step-title">Seguimiento</h3>
                        <p class="welcome-flow-step-desc">Consulta el historial completo, analiza métricas por categoría, ubicación o período y genera reportes para la toma de decisiones.</p>
                    </li>
                </ol>
            </div>
        </section>

        <!-- ═══════════════════════════════ BENEFITS ═══════════════════════════════ -->
        <section class="welcome-benefits" aria-labelledby="benefits-heading">
            <div class="welcome-section-inner">
                <header class="welcome-section-header">
                    <p class="welcome-section-overline">Diseñado para todos</p>
                    <h2 id="benefits-heading" class="welcome-section-title">Herramienta para cada rol</h2>
                </header>

                <div class="welcome-benefits-grid">
                    <!-- Reporters -->
                    <article class="welcome-benefit-card welcome-benefit-card--blue">
                        <div class="welcome-benefit-icon" aria-hidden="true">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M14.5 4h-5L7 7H4a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-3L14.5 4z"/>
                                <circle cx="12" cy="13" r="3"/>
                            </svg>
                        </div>
                        <h3 class="welcome-benefit-title">Reporteros</h3>
                        <p class="welcome-benefit-desc">Reporta cualquier incidencia en segundos escaneando el QR de la ubicación. Sin formularios complejos, sin fricción.</p>
                        <ul class="welcome-benefit-list" aria-label="Beneficios para reporteros">
                            <li>Reporte por QR sin login previo</li>
                            <li>Adjunta fotos como evidencia</li>
                            <li>Seguimiento en tiempo real</li>
                        </ul>
                    </article>

                    <!-- Maintenance -->
                    <article class="welcome-benefit-card welcome-benefit-card--amber">
                        <div class="welcome-benefit-icon" aria-hidden="true">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/>
                            </svg>
                        </div>
                        <h3 class="welcome-benefit-title">Equipo técnico</h3>
                        <p class="welcome-benefit-desc">Recibe tickets asignados con toda la información necesaria. Actualiza el estado desde cualquier dispositivo, en campo o en oficina.</p>
                        <ul class="welcome-benefit-list" aria-label="Beneficios para el equipo técnico">
                            <li>Cola de trabajo priorizada</li>
                            <li>Historial de incidencias por ubicación</li>
                            <li>Notificaciones de asignación</li>
                        </ul>
                    </article>

                    <!-- Admins -->
                    <article class="welcome-benefit-card welcome-benefit-card--emerald">
                        <div class="welcome-benefit-icon" aria-hidden="true">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M3 3h18v18H3z" rx="2"/><path d="M3 9h18M9 21V9"/>
                            </svg>
                        </div>
                        <h3 class="welcome-benefit-title">Administración</h3>
                        <p class="welcome-benefit-desc">Visión completa del estado operativo. Gestiona ubicaciones, categorías, usuarios y analiza patrones de incidencias para actuar de forma preventiva.</p>
                        <ul class="welcome-benefit-list" aria-label="Beneficios para administración">
                            <li>Dashboard con KPIs en tiempo real</li>
                            <li>Gestión de ubicaciones y QR</li>
                            <li>Control de acceso por roles</li>
                        </ul>
                    </article>
                </div>
            </div>
        </section>

        <!-- ═══════════════════════════════ FEATURES STRIP ═══════════════════════════════ -->
        <section class="welcome-features" aria-labelledby="features-heading">
            <div class="welcome-section-inner">
                <h2 id="features-heading" class="sr-only">Características técnicas</h2>
                <ul class="welcome-features-list" role="list">
                    <li class="welcome-feature-item">
                        <span class="welcome-feature-icon" aria-hidden="true">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                            </svg>
                        </span>
                        Acceso por roles
                    </li>
                    <li class="welcome-feature-sep" aria-hidden="true">·</li>
                    <li class="welcome-feature-item">
                        <span class="welcome-feature-icon" aria-hidden="true">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/>
                            </svg>
                        </span>
                        Tickets con estados
                    </li>
                    <li class="welcome-feature-sep" aria-hidden="true">·</li>
                    <li class="welcome-feature-item">
                        <span class="welcome-feature-icon" aria-hidden="true">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="3" width="18" height="18" rx="2"/><path d="M7 7h.01M17 7h.01M7 17h.01M17 17h.01M12 12h.01"/>
                            </svg>
                        </span>
                        Códigos QR por ubicación
                    </li>
                    <li class="welcome-feature-sep" aria-hidden="true">·</li>
                    <li class="welcome-feature-item">
                        <span class="welcome-feature-icon" aria-hidden="true">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                            </svg>
                        </span>
                        Categorización de incidencias
                    </li>
                    <li class="welcome-feature-sep" aria-hidden="true">·</li>
                    <li class="welcome-feature-item">
                        <span class="welcome-feature-icon" aria-hidden="true">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                            </svg>
                        </span>
                        Auditoría completa
                    </li>
                    <li class="welcome-feature-sep" aria-hidden="true">·</li>
                    <li class="welcome-feature-item">
                        <span class="welcome-feature-icon" aria-hidden="true">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="5" y="2" width="14" height="20" rx="2"/><path d="M12 18h.01"/>
                            </svg>
                        </span>
                        Optimizado para móvil
                    </li>
                </ul>
            </div>
        </section>

        <!-- ═══════════════════════════════ CTA BOTTOM ═══════════════════════════════ -->
        <section class="welcome-cta-section" aria-labelledby="cta-heading">
            <div class="welcome-section-inner">
                <div class="welcome-cta-box">
                    <div class="welcome-cta-box-blob" aria-hidden="true"></div>
                    <div class="welcome-cta-box-content">
                        <h2 id="cta-heading" class="welcome-cta-box-title">
                            Empieza a gestionar<br>incidencias hoy mismo
                        </h2>
                        <p class="welcome-cta-box-desc">Accede con tu cuenta o solicita al administrador crear tu usuario para comenzar a reportar.</p>
                        <div class="welcome-cta-box-actions">
                            @auth
                                <a href="{{ route('dashboard.index') }}" class="welcome-cta welcome-cta--primary welcome-cta--lg">
                                    Ir al panel de control
                                </a>
                            @else
                                @if (Route::has('login'))
                                    <a href="{{ route('login') }}" class="welcome-cta welcome-cta--primary welcome-cta--lg">
                                        Iniciar sesión
                                    </a>
                                @endif
                                @if (Route::has('register'))
                                    <a href="{{ route('register') }}" class="welcome-cta welcome-cta--outline welcome-cta--lg">
                                        Crear cuenta
                                    </a>
                                @endif
                            @endauth
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <!-- ═══════════════════════════════ FOOTER ═══════════════════════════════ -->
    <footer class="welcome-footer" role="contentinfo">
        <div class="welcome-section-inner">
            <div class="welcome-footer-inner">
                <p class="welcome-footer-brand">
                    <svg width="16" height="16" viewBox="0 0 28 28" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                        <rect width="28" height="28" rx="7" fill="#1e3a8a"/>
                        <circle cx="14" cy="14" r="3.5" fill="#3b82f6" stroke="#bfdbfe" stroke-width="1.5"/>
                        <circle cx="14" cy="14" r="1.2" fill="#fff"/>
                    </svg>
                    Incidencias OPS
                </p>
                <p class="welcome-footer-copy">Sistema de Reporte de Incidencias &copy; {{ date('Y') }}</p>
            </div>
        </div>
    </footer>

</body>
</html>