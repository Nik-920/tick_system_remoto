<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Autenticacion')</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />

    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
        <style>
           :root {
    /* Colores principales */
    --primary-50: #eff6ff;
    --primary-100: #dbeafe;
    --primary-600: #2563eb;
    --primary-700: #1d4ed8;
    
    /* Grises */
    --slate-50: #f8fafc;
    --slate-100: #f1f5f9;
    --slate-200: #e2e8f0;
    --slate-300: #cbd5e1;
    --slate-500: #64748b;
    --slate-700: #334155;
    --slate-900: #0f172a;
    
    /* Semánticos */
    --success-50: #f0fdf4;
    --success-100: #dcfce7;
    --success-700: #166534;
    
    --danger-50: #fef2f2;
    --danger-100: #fee2e2;
    --danger-700: #b91c1c;
    
    /* Espaciado */
    --spacing-md: 1rem;
    --spacing-lg: 1.5rem;
    
    /* Radios */
    --radius-md: 0.75rem;
    --radius-lg: 1rem;
    --radius-xl: 1.25rem;
    
    /* Transiciones */
    --transition-fast: 150ms cubic-bezier(0.4, 0, 0.2, 1);
}

* {
    box-sizing: border-box;
}

body.auth-page {
    margin: 0;
    min-height: 100vh;
    font-family: 'Instrument Sans', ui-sans-serif, system-ui, -apple-system, sans-serif;
    color: var(--slate-900);
    background: radial-gradient(circle at 20% 10%, var(--primary-100) 0%, transparent 40%),
                radial-gradient(circle at 80% 20%, var(--slate-100) 0%, transparent 50%),
                var(--slate-50);
    -webkit-font-smoothing: antialiased;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 1rem;
}

/* ========================================
   AUTH CONTAINER
======================================== */
.auth-shell {
    width: min(480px, 100%);
    margin: 2rem auto;
}

.auth-panel {
    background: white;
    border: 1px solid var(--slate-200);
    border-radius: var(--radius-xl);
    box-shadow: 0 20px 25px -5px rgb(0 0 0 / 0.1), 0 8px 10px -6px rgb(0 0 0 / 0.1);
    padding: 2rem;
}

.auth-panel h1 {
    margin: 0 0 0.5rem 0;
    font-size: 1.875rem;
    line-height: 1.2;
    font-weight: 800;
    color: var(--slate-900);
    letter-spacing: -0.025em;
}

.auth-panel p {
    margin: 0 0 1.5rem 0;
    color: var(--slate-500);
    font-size: 0.9375rem;
    line-height: 1.5;
}

.auth-panel form {
    margin-top: 1.5rem;
}

/* ========================================
   FORM ELEMENTS
======================================== */
.auth-panel label {
    display: block;
    margin-bottom: 0.5rem;
    color: var(--slate-700);
    font-size: 0.875rem;
    font-weight: 600;
}

.auth-panel input,
.auth-panel select,
.auth-panel textarea {
    width: 100%;
    border: 1px solid var(--slate-300);
    border-radius: var(--radius-md);
    padding: 0.75rem 1rem;
    background: var(--slate-50);
    color: var(--slate-900);
    font-size: 0.9375rem;
    transition: all var(--transition-fast);
}

.auth-panel input:hover,
.auth-panel select:hover,
.auth-panel textarea:hover {
    border-color: var(--slate-400);
}

.auth-panel input:focus,
.auth-panel select:focus,
.auth-panel textarea:focus {
    outline: 2px solid var(--primary-600);
    outline-offset: 0;
    border-color: var(--primary-600);
    background: white;
}

/* ========================================
   LINKS
======================================== */
.auth-panel a {
    color: var(--primary-600);
    text-decoration: none;
    font-weight: 600;
    transition: color var(--transition-fast);
}

.auth-panel a:hover {
    color: var(--primary-700);
    text-decoration: underline;
}

/* ========================================
   BUTTONS
======================================== */
.btn-primary {
    width: 100%;
    border: 0;
    border-radius: var(--radius-md);
    padding: 0.75rem 1.25rem;
    background: var(--primary-600);
    color: white;
    font-weight: 700;
    font-size: 0.9375rem;
    cursor: pointer;
    transition: all var(--transition-fast);
}

.btn-primary:hover {
    background: var(--primary-700);
    transform: translateY(-1px);
    box-shadow: 0 4px 12px -2px rgb(37 99 235 / 0.4);
}

.btn-primary:active {
    transform: translateY(0);
}

/* ========================================
   ALERTS
======================================== */
.alert-error,
.alert-success {
    border-radius: var(--radius-lg);
    padding: 1rem 1.25rem;
    font-size: 0.9375rem;
    font-weight: 500;
    border: 1px solid;
    margin-bottom: 1.5rem;
}

.alert-error {
    background: var(--danger-50);
    color: var(--danger-700);
    border-color: var(--danger-100);
}

.alert-success {
    background: var(--success-50);
    color: var(--success-700);
    border-color: var(--success-100);
}

/* ========================================
   UTILITIES
======================================== */
.stack {
    display: grid;
    gap: 1.25rem;
}

.actions {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 1rem;
    flex-wrap: wrap;
}

.muted-link {
    font-size: 0.875rem;
    color: var(--slate-500);
}

/* ========================================
   RESPONSIVE MOBILE
======================================== */
@media (max-width: 640px) {
    body.auth-page {
        padding: 0.5rem;
    }

    .auth-shell {
        margin: 1rem auto;
    }

    .auth-panel {
        padding: 1.5rem 1.25rem;
    }

    .auth-panel h1 {
        font-size: 1.5rem;
    }

    .auth-panel p {
        font-size: 0.875rem;
    }

    .actions {
        flex-direction: column;
        align-items: stretch;
    }

    .muted-link {
        text-align: center;
    }
}

@media (max-width: 380px) {
    .auth-panel {
        padding: 1.25rem 1rem;
    }

    .auth-panel h1 {
        font-size: 1.375rem;
    }
}
        </style>
    @endif
</head>
<body class="auth-page min-h-screen bg-slate-100 text-slate-900">
<main class="auth-shell max-w-xl mx-auto p-4 md:py-12">
    <section class="auth-panel bg-white border border-slate-200 rounded-2xl shadow-xl shadow-slate-900/10 p-6 md:p-8">
        @yield('content')
    </section>
</main>
</body>
</html>
