{{-- DomPDF-safe stylesheet (no flexbox/grid; tables + block boxes only). --}}
<style>
    * { box-sizing: border-box; }
    body {
        font-family: 'DejaVu Sans', sans-serif;
        color: #1e293b;
        font-size: 11px;
        line-height: 1.45;
        margin: 0;
    }
    .report { padding: 0; }

    .r-head {
        border-bottom: 3px solid #d97706;
        padding-bottom: 10px;
        margin-bottom: 14px;
    }
    .r-kicker {
        font-size: 9px;
        letter-spacing: 1.5px;
        text-transform: uppercase;
        color: #b45309;
        font-weight: bold;
        margin: 0 0 2px;
    }
    .r-title {
        font-size: 20px;
        font-weight: bold;
        color: #0f172a;
        margin: 0 0 6px;
    }
    .r-meta { width: 100%; border-collapse: collapse; }
    .r-meta td {
        font-size: 10px;
        color: #475569;
        padding: 1px 0;
        vertical-align: top;
    }
    .r-meta td.k {
        color: #94a3b8;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        font-size: 8.5px;
        width: 90px;
        font-weight: bold;
    }

    .r-section { margin: 0 0 16px; }
    .r-h2 {
        font-size: 12px;
        font-weight: bold;
        color: #0f172a;
        border-left: 4px solid #d97706;
        padding-left: 7px;
        margin: 0 0 7px;
    }
    .r-note { font-size: 9.5px; color: #64748b; margin: 0 0 7px; }

    /* KPI grid as a table */
    .kpi-grid { width: 100%; border-collapse: separate; border-spacing: 6px; }
    .kpi-cell {
        width: 33%;
        border: 1px solid #e2e8f0;
        border-radius: 6px;
        background: #f8fafc;
        padding: 7px 9px;
        vertical-align: top;
    }
    .kpi-label {
        font-size: 8px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: #64748b;
        font-weight: bold;
        margin: 0 0 3px;
    }
    .kpi-val { font-size: 17px; font-weight: bold; color: #0f172a; margin: 0; }
    .kpi-hint { font-size: 8px; color: #94a3b8; margin: 3px 0 0; }

    /* Data tables */
    .dt { width: 100%; border-collapse: collapse; }
    .dt th {
        background: #fff7ed;
        color: #9a3412;
        font-size: 8.5px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        text-align: left;
        padding: 5px 7px;
        border-bottom: 1px solid #fed7aa;
    }
    .dt td {
        font-size: 10px;
        padding: 5px 7px;
        border-bottom: 1px solid #eef2f6;
        color: #334155;
        vertical-align: middle;
    }
    .dt td.num { text-align: right; font-variant-numeric: tabular-nums; }

    /* Bars */
    .bar {
        height: 8px;
        background: #f1f5f9;
        border-radius: 4px;
        width: 100%;
    }
    .bar > div {
        height: 8px;
        border-radius: 4px;
        background: #d97706;
    }

    /* Pills */
    .pill {
        display: inline-block;
        padding: 1px 6px;
        border-radius: 8px;
        font-size: 8.5px;
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

    .reco { margin: 0; padding-left: 16px; }
    .reco li { font-size: 10px; color: #334155; margin-bottom: 3px; }

    .defs { margin: 0; padding: 0; list-style: none; }
    .defs li { font-size: 8.5px; color: #64748b; margin-bottom: 2px; }
    .defs b { color: #475569; }

    .empty { font-size: 9.5px; color: #94a3b8; font-style: italic; }

    .foot {
        margin-top: 10px;
        padding-top: 7px;
        border-top: 1px solid #e2e8f0;
        font-size: 8px;
        color: #94a3b8;
    }
</style>
