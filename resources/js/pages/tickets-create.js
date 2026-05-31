export function init() {
    const form = document.querySelector('.tickets-create-form');
    if (!form) {
        return;
    }

    const locationSelect = document.getElementById('location_id');
    const categorySelect = document.getElementById('category_id');
    const fileInput = document.getElementById('media_files');
    const priorityInputs = Array.from(form.querySelectorAll('input[name="priority"]'));

    const summaryLocation = document.querySelector('[data-summary="location"]');
    const summaryCategory = document.querySelector('[data-summary="category"]');
    const summaryPriority = document.querySelector('[data-summary="priority"]');
    const summaryAttachments = document.querySelector('[data-summary="attachments"]');
    const uploadList = document.getElementById('ticketsUploadList');

    function getSelectedOption(select) {
        if (!select) return '';
        const option = select.selectedOptions?.[0];
        if (!option || !option.value) return '';
        return option.textContent.trim();
    }

    function renderFileList(files) {
        if (!uploadList) return;
        uploadList.replaceChildren();

        if (!files.length) {
            const empty = document.createElement('p');
            empty.className = 'tickets-upload-empty';
            empty.textContent = 'No hay archivos seleccionados.';
            uploadList.appendChild(empty);
            return;
        }

        const count = document.createElement('p');
        count.className = 'tickets-upload-count';
        count.textContent = `${files.length} archivo${files.length === 1 ? '' : 's'} seleccionado${files.length === 1 ? '' : 's'}`;
        uploadList.appendChild(count);

        const list = document.createElement('ul');
        list.className = 'tickets-upload-files';

        files.forEach((file) => {
            const item = document.createElement('li');
            item.className = 'tickets-upload-file';
            item.textContent = file.name;
            list.appendChild(item);
        });

        uploadList.appendChild(list);
    }

    function updateSummary() {
        if (summaryLocation) {
            const label = getSelectedOption(locationSelect) || 'Sin seleccionar';
            summaryLocation.textContent = label;
        }

        if (summaryCategory) {
            const label = getSelectedOption(categorySelect) || 'Sin seleccionar';
            summaryCategory.textContent = label;
        }

        if (summaryPriority) {
            const selected = priorityInputs.find((input) => input.checked);
            summaryPriority.textContent = selected?.dataset.label || 'Media';
        }

        if (summaryAttachments) {
            const total = fileInput?.files?.length ?? 0;
            summaryAttachments.textContent = total ? `${total} archivo${total === 1 ? '' : 's'}` : 'Sin adjuntos';
        }
    }

    function handleFileChange() {
        const files = fileInput?.files ? Array.from(fileInput.files) : [];
        renderFileList(files);
        updateSummary();
    }

    locationSelect?.addEventListener('change', updateSummary);
    categorySelect?.addEventListener('change', updateSummary);
    priorityInputs.forEach((input) => input.addEventListener('change', updateSummary));
    fileInput?.addEventListener('change', handleFileChange);

    form.addEventListener('submit', () => {
        const buttons = form.querySelectorAll('button[type="submit"]');
        buttons.forEach((button) => {
            button.disabled = true;
            button.setAttribute('aria-disabled', 'true');
        });
    }, { once: true });

    updateSummary();
    handleFileChange();
}

export default { init };
