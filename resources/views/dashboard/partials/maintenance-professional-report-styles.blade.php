{{-- DomPDF-safe stylesheet for the professional maintenance report.
     No flexbox/grid: tables + block boxes only. Documental tone. --}}
<style>
    * { box-sizing: border-box; }
    body {
        font-family: 'DejaVu Sans', sans-serif;
        color: #1e293b;
        font-size: 10px;
        line-height: 1.45;
        margin: 0;
    }
    .report { padding: 0; }

    /* ── Cover ─────────────────────────────────────────────── */
    .cover { padding: 90px 10px 0; page-break-after: always; }
    .cover-kicker {
        font-size: 10px;
        letter-spacing: 2px;
        text-transform: uppercase;
        color: #b45309;
        font-weight: bold;
        margin: 0 0 6px;
    }
    .cover-title { font-size: 26px; font-weight: bold; color: #0f172a; margin: 0 0 4px; }
    .cover-subtitle { font-size: 12px; color: #475569; margin: 0 0 28px; }
    .cover-meta { width: 100%; border-collapse: collapse; margin-top: 18px; }
    .cover-meta td {
        font-size: 11px;
        color: #334155;
        padding: 6px 8px;
        border-bottom: 1px solid #e2e8f0;
        vertical-align: top;
    }
    .cover-meta td.k {
        color: #94a3b8;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        font-size: 8.5px;
        width: 150px;
        font-weight: bold;
    }
    .cover-note {
        margin-top: 30px;
        font-size: 9px;
        color: #64748b;
        border-left: 3px solid #d97706;
        padding-left: 8px;
    }

    /* ── Sections ──────────────────────────────────────────── */
    .r-section { margin: 0 0 16px; }
    .r-h2 {
        font-size: 12.5px;
        font-weight: bold;
        color: #0f172a;
        border-left: 4px solid #d97706;
        padding-left: 7px;
        margin: 0 0 7px;
    }
    .r-note { font-size: 9px; color: #64748b; margin: 0 0 7px; }
    .clock-tag {
        display: inline-block;
        padding: 0 5px;
        border-radius: 7px;
        font-size: 7.5px;
        font-weight: bold;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        background: #f1f5f9;
        color: #475569;
    }
    .clock-snapshot { background: #e0f2fe; color: #075985; }
    .clock-periodo { background: #fef9c3; color: #854d0e; }

    /* ── Headline figures (executive summary) ─────────────── */
    .headline-grid { width: 100%; border-collapse: separate; border-spacing: 5px; }
    .headline-cell {
        width: 16%;
        border: 1px solid #e2e8f0;
        border-radius: 6px;
        background: #f8fafc;
        padding: 6px 8px;
        vertical-align: top;
        text-align: center;
    }
    .headline-val { font-size: 16px; font-weight: bold; color: #0f172a; margin: 0; }
    .headline-label {
        font-size: 7.5px;
        text-transform: uppercase;
        letter-spacing: 0.4px;
        color: #64748b;
        font-weight: bold;
        margin: 2px 0 0;
    }
    .narrative { margin: 8px 0 0; padding-left: 16px; }
    .narrative li { font-size: 10px; color: #334155; margin-bottom: 3px; }

    /* ── Data tables ───────────────────────────────────────── */
    .dt { width: 100%; border-collapse: collapse; }
    .dt th {
        background: #fff7ed;
        color: #9a3412;
        font-size: 8px;
        text-transform: uppercase;
        letter-spacing: 0.4px;
        text-align: left;
        padding: 4px 6px;
        border-bottom: 1px solid #fed7aa;
    }
    .dt td {
        font-size: 9px;
        padding: 4px 6px;
        border-bottom: 1px solid #eef2f6;
        color: #334155;
        vertical-align: top;
    }
    .dt td.num { text-align: right; font-variant-numeric: tabular-nums; }
    .dt tr { page-break-inside: avoid; }
    .muted { color: #94a3b8; font-size: 8px; }

    /* ── Pills ─────────────────────────────────────────────── */
    .pill {
        display: inline-block;
        padding: 1px 6px;
        border-radius: 8px;
        font-size: 8px;
        font-weight: bold;
    }
    .pill-critical { background: #fee2e2; color: #991b1b; }
    .pill-high { background: #ffedd5; color: #9a3412; }
    .pill-medium { background: #fefce8; color: #854d0e; }
    .pill-low { background: #f0fdf4; color: #166534; }
    .pill-open { background: #dbeafe; color: #1e40af; }
    .pill-in_progress { background: #fef3c7; color: #92400e; }
    .pill-resolved { background: #dcfce7; color: #166534; }
    .pill-rejected { background: #f1f5f9; color: #475569; }
    .pill-cancelled { background: #fae8ff; color: #86198f; }
    .pill-warning { background: #fef3c7; color: #92400e; }
    .pill-info { background: #e0f2fe; color: #075985; }
    .pill-ok { background: #dcfce7; color: #166534; }

    /* ── Lists ─────────────────────────────────────────────── */
    .reco { margin: 0; padding-left: 16px; }
    .reco li { font-size: 9.5px; color: #334155; margin-bottom: 3px; }

    .defs { margin: 0; padding: 0; list-style: none; }
    .defs li { font-size: 8.5px; color: #64748b; margin-bottom: 2.5px; page-break-inside: avoid; }
    .defs b { color: #475569; }

    .empty { font-size: 9px; color: #94a3b8; font-style: italic; }

    .low-sample {
        display: inline-block;
        padding: 0 5px;
        border-radius: 7px;
        font-size: 7.5px;
        font-weight: bold;
        background: #fef3c7;
        color: #92400e;
    }

    /* ── AI duplicate alert blocks ─────────────────────────── */
    .dup-block {
        border: 1px solid #e2e8f0;
        border-left: 3px solid #d97706;
        border-radius: 5px;
        background: #fffbf5;
        padding: 6px 9px;
        margin-bottom: 6px;
        page-break-inside: avoid;
    }
    .dup-title { font-size: 9.5px; font-weight: bold; color: #0f172a; margin: 0 0 2px; }
    .dup-meta { font-size: 8.5px; color: #475569; margin: 0 0 3px; }
    .dup-summary { font-size: 9px; color: #334155; margin: 0 0 3px; }
    .dup-reasons { margin: 0; padding-left: 14px; }
    .dup-reasons li { font-size: 8.5px; color: #475569; margin-bottom: 1px; }
    .dup-action { font-size: 8.5px; color: #9a3412; font-weight: bold; margin: 3px 0 0; }

    .page-break { page-break-before: always; }

    .foot {
        margin-top: 12px;
        padding-top: 7px;
        border-top: 1px solid #e2e8f0;
        font-size: 8px;
        color: #94a3b8;
    }
</style>
