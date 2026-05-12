export function open(id) {
    const el = document.getElementById(id);
    if (el) el.style.display = 'block';
}

export function close(id) {
    const el = document.getElementById(id);
    if (el) el.style.display = 'none';
}

export default { open, close };
