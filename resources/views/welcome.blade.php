<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tick System Onn — Sistema de Gestión</title>
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
            <a href="{{ url('/') }}" class="welcome-brand" aria-label="Inicio — Tick System Onn">
                <span class="welcome-brand-icon" aria-hidden="true">
                    <x-lucide-shield width="24" height="24" stroke="white" stroke-width="2.5" />
                </span>
                <span class="welcome-brand-name">
                    <span class="welcome-brand-primary">Tick System</span>
                    <span class="welcome-brand-suffix">&nbsp;Onn</span>
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
                            <x-lucide-layout-grid width="18" height="18" stroke-width="2.2" aria-hidden="true" />
                            Ir al panel
                        </a>
                    @else
                        @if (Route::has('register'))
                            <a href="{{ route('register') }}" class="welcome-cta welcome-cta--primary">
                                <x-lucide-plus width="18" height="18" stroke-width="2.2" aria-hidden="true" />
                                Crear cuenta
                            </a>
                        @endif
                        @if (Route::has('login'))
                            <a href="{{ route('login') }}" class="welcome-cta welcome-cta--ghost">
                                Iniciar sesión
                                <x-lucide-arrow-right width="16" height="16" stroke-width="2.2" aria-hidden="true" />
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
                            <x-lucide-alert-circle width="22" height="22" stroke-width="2" />
                        </div>
                        <h3 class="welcome-flow-step-title">Reportar</h3>
                        <p class="welcome-flow-step-desc">El usuario escanea el código QR de la ubicación o accede al sistema y registra el incidente con categoría, descripción y evidencia fotográfica.</p>
                    </li>

                    <li class="welcome-flow-step">
                        <div class="welcome-flow-step-num" aria-hidden="true">02</div>
                        <div class="welcome-flow-step-icon" aria-hidden="true">
                            <x-lucide-user-check width="22" height="22" stroke-width="2" />
                        </div>
                        <h3 class="welcome-flow-step-title">Asignar</h3>
                        <p class="welcome-flow-step-desc">El equipo administrativo revisa el ticket, lo clasifica por prioridad y lo asigna al técnico o área responsable de atender el incidente.</p>
                    </li>

                    <li class="welcome-flow-step">
                        <div class="welcome-flow-step-num" aria-hidden="true">03</div>
                        <div class="welcome-flow-step-icon" aria-hidden="true">
                            <x-lucide-wrench width="22" height="22" stroke-width="2" />
                        </div>
                        <h3 class="welcome-flow-step-title">Resolver</h3>
                        <p class="welcome-flow-step-desc">El técnico atiende el incidente en campo, actualiza el estado del ticket y documenta las acciones correctivas realizadas para el cierre.</p>
                    </li>

                    <li class="welcome-flow-step">
                        <div class="welcome-flow-step-num" aria-hidden="true">04</div>
                        <div class="welcome-flow-step-icon" aria-hidden="true">
                            <x-lucide-activity width="22" height="22" stroke-width="2" />
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
                            <x-lucide-camera width="24" height="24" stroke-width="2" />
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
                            <x-lucide-wrench width="24" height="24" stroke-width="2" />
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
                            <x-lucide-layout-dashboard width="24" height="24" stroke-width="2" />
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
                            <x-lucide-lock width="18" height="18" stroke-width="2.2" />
                        </span>
                        Acceso por roles
                    </li>
                    <li class="welcome-feature-sep" aria-hidden="true">·</li>
                    <li class="welcome-feature-item">
                        <span class="welcome-feature-icon" aria-hidden="true">
                            <x-lucide-layers width="18" height="18" stroke-width="2.2" />
                        </span>
                        Tickets con estados
                    </li>
                    <li class="welcome-feature-sep" aria-hidden="true">·</li>
                    <li class="welcome-feature-item">
                        <span class="welcome-feature-icon" aria-hidden="true">
                            <x-lucide-qr-code width="18" height="18" stroke-width="2.2" />
                        </span>
                        Códigos QR por ubicación
                    </li>
                    <li class="welcome-feature-sep" aria-hidden="true">·</li>
                    <li class="welcome-feature-item">
                        <span class="welcome-feature-icon" aria-hidden="true">
                            <x-lucide-message-square width="18" height="18" stroke-width="2.2" />
                        </span>
                        Categorización de incidencias
                    </li>
                    <li class="welcome-feature-sep" aria-hidden="true">·</li>
                    <li class="welcome-feature-item">
                        <span class="welcome-feature-icon" aria-hidden="true">
                            <x-lucide-shield width="18" height="18" stroke-width="2.2" />
                        </span>
                        Auditoría completa
                    </li>
                    <li class="welcome-feature-sep" aria-hidden="true">·</li>
                    <li class="welcome-feature-item">
                        <span class="welcome-feature-icon" aria-hidden="true">
                            <x-lucide-smartphone width="18" height="18" stroke-width="2.2" />
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
                    <x-lucide-shield width="18" height="18" stroke="#3b82f6" stroke-width="2.5" aria-hidden="true" />
                    Tick System Onn
                </p>
                <p class="welcome-footer-copy">Tick System Onn &copy; {{ date('Y') }}</p>
            </div>
        </div>
    </footer>

</body>
</html>