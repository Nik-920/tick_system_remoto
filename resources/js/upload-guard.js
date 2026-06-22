/**
 * upload-guard.js
 *
 * Client-side validation for file inputs before form submission.
 * Reads limits from data attributes so PHP/blade is the single source of truth.
 *
 * Data attributes on the <input type="file"> element:
 *   data-upload-guard              — required to activate the guard
 *   data-max-file-size             — max bytes per file (e.g. 10485760 for 10 MB)
 *   data-max-file-size-label       — human-readable label (e.g. "10 MB")
 *   data-max-files                 — max number of files
 *   data-max-total-size            — max bytes total across all files
 *   data-max-total-size-label      — human-readable label (e.g. "50 MB")
 *   data-allowed-extensions        — comma-separated (e.g. "jpg,jpeg,png,webp,pdf")
 */

function getModal() {
    return document.querySelector('[data-upload-error-modal]');
}

function showUploadError(messages) {
    const modal = getModal();
    if (!modal) return;

    const body = modal.querySelector('[data-upload-error-body]');
    if (body) {
        body.replaceChildren();
        if (messages.length === 1) {
            const p = document.createElement('p');
            p.textContent = messages[0];
            body.appendChild(p);
        } else {
            const ul = document.createElement('ul');
            ul.className = 'upload-error-modal__list';
            messages.forEach((msg) => {
                const li = document.createElement('li');
                li.textContent = msg;
                ul.appendChild(li);
            });
            body.appendChild(ul);
        }
    }

    modal.removeAttribute('hidden');
    modal.querySelector('[data-upload-error-close]')?.focus();
}

function closeModal() {
    const modal = getModal();
    if (modal) modal.setAttribute('hidden', '');
}

function validateFiles(input) {
    const maxFileSize    = parseInt(input.dataset.maxFileSize    || '0', 10);
    const maxFileSizeLabel = input.dataset.maxFileSizeLabel      || '';
    const maxFiles       = parseInt(input.dataset.maxFiles       || '0', 10);
    const maxTotalSize   = parseInt(input.dataset.maxTotalSize   || '0', 10);
    const maxTotalSizeLabel = input.dataset.maxTotalSizeLabel    || '';
    const allowedExts    = (input.dataset.allowedExtensions || '')
        .split(',').map((e) => e.trim().toLowerCase()).filter(Boolean);

    const files = Array.from(input.files || []);
    const errors = [];

    if (maxFiles > 0 && files.length > maxFiles) {
        errors.push(`Seleccionaste ${files.length} archivos, pero el máximo permitido es ${maxFiles}.`);
        return errors;
    }

    let totalBytes = 0;

    for (const file of files) {
        if (maxFileSize > 0 && file.size > maxFileSize) {
            const sizeMb = (file.size / 1024 / 1024).toFixed(1);
            errors.push(`El archivo "${file.name}" pesa ${sizeMb} MB y supera el límite de ${maxFileSizeLabel || (maxFileSize / 1024 / 1024).toFixed(0) + ' MB'}.`);
        }

        if (allowedExts.length > 0) {
            const nameParts = file.name.split('.');
            const ext = (nameParts.length > 1 ? nameParts[nameParts.length - 1] : '').toLowerCase();
            if (!allowedExts.includes(ext)) {
                const friendly = allowedExts.map((e) => e.toUpperCase()).join(', ');
                errors.push(`El archivo "${file.name}" no es compatible. Formatos permitidos: ${friendly}.`);
            }
        }

        totalBytes += file.size;
    }

    if (maxTotalSize > 0 && totalBytes > maxTotalSize) {
        const totalMb = (totalBytes / 1024 / 1024).toFixed(1);
        errors.push(`El total seleccionado pesa ${totalMb} MB y supera el límite de ${maxTotalSizeLabel || (maxTotalSize / 1024 / 1024).toFixed(0) + ' MB'}.`);
    }

    return errors;
}

function bindGuardInput(input) {
    input.addEventListener('change', () => {
        const errors = validateFiles(input);
        if (errors.length > 0) {
            showUploadError(errors);
            input.value = '';
        }
    });
}

function bindGuardForm(form) {
    form.addEventListener('submit', (e) => {
        const guards = form.querySelectorAll('[data-upload-guard]');
        const allErrors = [];
        guards.forEach((input) => {
            allErrors.push(...validateFiles(input));
        });
        if (allErrors.length > 0) {
            e.preventDefault();
            showUploadError(allErrors);
        }
    });
}

function bindModalClose(modal) {
    modal.querySelectorAll('[data-upload-error-close]').forEach((el) => {
        el.addEventListener('click', closeModal);
    });

    modal.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') closeModal();
    });
}

export function init() {
    document.querySelectorAll('[data-upload-guard]').forEach(bindGuardInput);

    document.querySelectorAll('form').forEach((form) => {
        if (form.querySelector('[data-upload-guard]')) {
            bindGuardForm(form);
        }
    });

    const modal = getModal();
    if (modal) bindModalClose(modal);
}
