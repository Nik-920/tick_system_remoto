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
                --slate-900: #0f172a;
                --slate-700: #334155;
                --slate-500: #64748b;
                --slate-200: #e2e8f0;
                --blue-600: #2563eb;
                --blue-700: #1d4ed8;
                --red-100: #fee2e2;
                --red-700: #b91c1c;
                --green-100: #dcfce7;
                --green-700: #166534;
            }

            * {
                box-sizing: border-box;
            }

            body.auth-page {
                margin: 0;
                min-height: 100vh;
                font-family: 'Instrument Sans', system-ui, -apple-system, sans-serif;
                color: var(--slate-900);
                background: radial-gradient(circle at 10% -5%, #dbeafe 0%, transparent 30%),
                    radial-gradient(circle at 90% 10%, #e2e8f0 0%, transparent 35%),
                    #f8fafc;
            }

            .auth-shell {
                width: min(520px, calc(100% - 2rem));
                margin: 0 auto;
                padding: 2.5rem 0;
            }

            .auth-panel {
                background: #fff;
                border: 1px solid var(--slate-200);
                border-radius: 18px;
                box-shadow: 0 20px 30px -24px rgba(15, 23, 42, 0.55);
                padding: 1.5rem;
            }

            .auth-panel h1 {
                margin: 0;
                font-size: 1.65rem;
                line-height: 1.2;
            }

            .auth-panel p {
                margin: 0;
                color: var(--slate-500);
            }

            .auth-panel form {
                margin-top: 1rem;
            }

            .auth-panel label {
                display: block;
                margin-bottom: 0.45rem;
                color: var(--slate-700);
                font-size: 0.875rem;
                font-weight: 600;
            }

            .auth-panel input,
            .auth-panel select,
            .auth-panel textarea {
                width: 100%;
                border: 1px solid #cbd5e1;
                border-radius: 10px;
                padding: 0.62rem 0.75rem;
                background: #f8fafc;
                color: var(--slate-900);
            }

            .auth-panel input:focus,
            .auth-panel select:focus,
            .auth-panel textarea:focus {
                outline: 2px solid #93c5fd;
                border-color: #60a5fa;
                background: #fff;
            }

            .auth-panel a {
                color: var(--blue-600);
                text-decoration: none;
            }

            .auth-panel a:hover {
                color: var(--blue-700);
                text-decoration: underline;
            }

            .btn-primary {
                border: 0;
                border-radius: 10px;
                padding: 0.62rem 1rem;
                background: var(--blue-600);
                color: #fff;
                font-weight: 600;
                cursor: pointer;
            }

            .btn-primary:hover {
                background: var(--blue-700);
            }

            .alert-error,
            .alert-success {
                border-radius: 12px;
                padding: 0.8rem 0.95rem;
                font-size: 0.93rem;
            }

            .alert-error {
                background: var(--red-100);
                color: var(--red-700);
                border: 1px solid #fecaca;
            }

            .alert-success {
                background: var(--green-100);
                color: var(--green-700);
                border: 1px solid #bbf7d0;
            }

            .stack {
                display: grid;
                gap: 0.95rem;
            }

            .actions {
                display: flex;
                justify-content: space-between;
                align-items: center;
                gap: 1rem;
            }

            .muted-link {
                font-size: 0.9rem;
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
