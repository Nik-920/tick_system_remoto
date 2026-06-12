/**
 * Página: detalle de ticket (/tickets/{ticket}).
 * - Copiar código del ticket.
 * - Confirmación + bloqueo de doble submit en formularios sensibles.
 * - Modal de eliminación.
 * - Selector de evidencias: lista de archivos elegidos y quitar antes de enviar.
 *
 * Sin frameworks: JS vanilla, cargado vía el page-loader de app.js.
 */

function initCopyButtons() {
    document.querySelectorAll('[data-copy-ticket]').forEach((btn) => {
        btn.addEventListener('click', async () => {
            const value = btn.dataset.copyTicket;
            if (!value) return;
            try {
                await navigator.clipboard.writeText(value);
                const label = btn.querySelector('.copy-label');
                if (label) {
                    label.textContent = 'Copiado';
                    btn.classList.add('is-copied');
                    setTimeout(() => {
                        label.textContent = 'Copiar';
                        btn.classList.remove('is-copied');
                    }, 1500);
                }
            } catch {
                // fallback silencioso
            }
        });
    });
}

function initOnceForms() {
    document.querySelectorAll('form.tickets-once-form, form.tickets-review-actions').forEach((form) => {
        form.addEventListener('submit', (e) => {
            const message = form.dataset.confirm;
            if (message && !globalThis.confirm(message)) {
                e.preventDefault();
                return;
            }
            // Deshabilitar submits internos y externos (form="...") tras enviar.
            const buttons = [
                ...form.querySelectorAll('button[type="submit"]'),
                ...(form.id ? document.querySelectorAll(`button[type="submit"][form="${form.id}"]`) : []),
            ];
            buttons.forEach((button) => {
                button.disabled = true;
                button.setAttribute('aria-disabled', 'true');
            });
        });
    });
}

function initDeleteModal() {
    const modal = document.getElementById('deleteTicketModal');
    if (!modal) return;
    const modalContent = modal.querySelector('.relative');

    const open = () => {
        modal.classList.remove('hidden');
        modal.getBoundingClientRect(); // Forzar reflow para que la transición de opacidad se anime.
        modal.classList.remove('opacity-0');
        modal.classList.add('opacity-100');
        modalContent.classList.remove('scale-95', 'translate-y-4');
        modalContent.classList.add('scale-100', 'translate-y-0');
    };

    const close = () => {
        modal.classList.remove('opacity-100');
        modal.classList.add('opacity-0');
        modalContent.classList.remove('scale-100', 'translate-y-0');
        modalContent.classList.add('scale-95', 'translate-y-4');
        setTimeout(() => modal.classList.add('hidden'), 300);
    };

    document.querySelectorAll('[data-open-delete-modal]').forEach((btn) => btn.addEventListener('click', open));
    modal.querySelectorAll('[data-close-delete-modal]').forEach((btn) => btn.addEventListener('click', close));

    const confirmBtn = modal.querySelector('[data-confirm-delete-ticket]');
    confirmBtn?.addEventListener('click', () => {
        const form = document.getElementById('delete-ticket-form');
        if (form) {
            confirmBtn.disabled = true;
            form.submit();
        }
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && !modal.classList.contains('hidden')) close();
    });
}

function removeFileAt(input, index) {
    const transfer = new DataTransfer();
    [...input.files].forEach((f, i) => {
        if (i !== index) transfer.items.add(f);
    });
    input.files = transfer.files;
}

function createFileItem(input, file, index, rerender) {
    const item = document.createElement('div');
    item.className = 'ticket-show__file-item';

    const name = document.createElement('span');
    name.className = 'truncate';
    name.textContent = `${file.name} (${(file.size / 1024 / 1024).toFixed(2)} MB)`;
    name.title = file.name;

    const remove = document.createElement('button');
    remove.type = 'button';
    remove.className = 'ticket-show__file-remove';
    remove.setAttribute('aria-label', `Quitar ${file.name}`);
    remove.textContent = '✕';
    remove.addEventListener('click', () => {
        removeFileAt(input, index);
        rerender();
    });

    item.append(name, remove);
    return item;
}

function renderSelectedFiles(input, list) {
    list.innerHTML = '';
    const files = [...input.files];
    list.classList.toggle('hidden', files.length === 0);

    files.forEach((file, index) => {
        list.appendChild(createFileItem(input, file, index, () => renderSelectedFiles(input, list)));
    });
}

function bindDropzone(dropzone, input, rerender) {
    ['dragenter', 'dragover'].forEach((evt) =>
        dropzone.addEventListener(evt, (e) => {
            e.preventDefault();
            dropzone.classList.add('is-dragover');
        })
    );
    ['dragleave', 'drop'].forEach((evt) =>
        dropzone.addEventListener(evt, (e) => {
            e.preventDefault();
            dropzone.classList.remove('is-dragover');
        })
    );
    dropzone.addEventListener('drop', (e) => {
        const dropped = e.dataTransfer?.files;
        if (!dropped?.length) return;
        const transfer = new DataTransfer();
        [...input.files, ...dropped].forEach((f) => transfer.items.add(f));
        input.files = transfer.files;
        rerender();
    });
}

function initEvidenceUploader() {
    const wrapper = document.querySelector('[data-evidence-uploader]');
    if (!wrapper) return;

    const input = wrapper.querySelector('input[type="file"]');
    const list = wrapper.querySelector('[data-evidence-list]');
    const dropzone = wrapper.querySelector('[data-evidence-dropzone]');
    if (!input || !list) return;

    const rerender = () => renderSelectedFiles(input, list);

    input.addEventListener('change', rerender);

    if (dropzone) {
        bindDropzone(dropzone, input, rerender);
    }
}

export function init() {
    initCopyButtons();
    initOnceForms();
    initDeleteModal();
    initEvidenceUploader();
}
