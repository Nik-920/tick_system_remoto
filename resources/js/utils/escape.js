const REPLACEMENTS = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' };

export function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, (c) => REPLACEMENTS[c]);
}

// Only allows absolute http/https or root-relative paths; rejects javascript: and data: schemes.
export function safeUrl(url) {
    if (!url) return '#';
    const s = String(url).trim();
    if (/^https?:\/\//i.test(s) || s.startsWith('/')) return s;
    return '#';
}
