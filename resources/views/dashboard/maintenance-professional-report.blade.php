@php
    /** @var \App\ViewModels\Dashboard\MaintenanceReportViewModel $vm */
    $period = $vm->period;
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Informe profesional de mantenimiento · {{ $period->fromDate() }} al {{ $period->toDate() }}</title>
    @include('dashboard.partials.maintenance-professional-report-styles')
    <style>
        body { background: #eef2f6; padding: 0; }
        .preview-toolbar {
            max-width: 900px;
            margin: 0 auto 14px;
            padding: 0 8px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .preview-toolbar a {
            display: inline-block;
            padding: 8px 14px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 700;
            text-decoration: none;
        }
        .tb-primary { background: #d97706; color: #fff; }
        .tb-ghost { background: #fff; color: #334155; border: 1px solid #e2e8f0; }
        .tb-spacer { flex: 1; }
        .preview-page { max-width: 900px; margin: 0 auto; padding: 0 8px 48px; }
        .preview-sheet {
            background: #fff;
            padding: 30px 32px;
            border-radius: 10px;
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.10);
        }
        /* On screen the cover is a header, not a separate page. */
        .cover { padding: 0 0 18px; page-break-after: avoid; border-bottom: 3px solid #d97706; margin-bottom: 18px; }
        .page-break { page-break-before: avoid; }
        @media print {
            .preview-toolbar { display: none; }
            body { background: #fff; }
            .preview-page { padding: 0; }
            .preview-sheet { box-shadow: none; border-radius: 0; padding: 0; }
        }
    </style>
</head>
<body>
    <div class="preview-toolbar">
        <a class="tb-ghost" href="{{ route('dashboard.index', $period->toQueryParams()) }}">Volver al dashboard</a>
        <span class="tb-spacer"></span>
        <a class="tb-ghost" href="javascript:window.print()">Imprimir</a>
        <a class="tb-primary" href="{{ route('dashboard.maintenance.report.pdf', $period->toQueryParams()) }}">Descargar PDF</a>
    </div>
    <div class="preview-page">
        <div class="preview-sheet">
            @include('dashboard.partials.maintenance-professional-report-body')
        </div>
    </div>
</body>
</html>
