// Upload form: submit as soon as a file is chosen; the button stays as a fallback.
document.querySelectorAll('input[data-auto-submit]').forEach((input) => {
    const error = input.form.querySelector('[data-file-error]');

    input.addEventListener('change', () => {
        const file = input.files[0];
        if (!file) {
            return;
        }

        const maxBytes = Number(input.dataset.maxBytes);
        if (maxBytes && file.size > maxBytes) {
            error.textContent = `The file may not be larger than ${Math.round(maxBytes / 1024 / 1024)} MB.`;
            error.hidden = false;
            return;
        }

        error.hidden = true;
        input.form.requestSubmit();
    });
});

// Datagrid: click a column header to sort by it, click again to reverse.
document.querySelectorAll('table[data-sortable]').forEach((table) => {
    const body = table.tBodies[0];
    const headers = [...table.tHead.rows[0].cells];

    headers.forEach((header) => {
        header.querySelector('[data-sort]').addEventListener('click', () => {
            const ascending = header.getAttribute('aria-sort') !== 'ascending';
            const column = header.cellIndex;
            const value = (row) => Number(row.cells[column].dataset.value ?? row.cells[column].textContent);

            headers.forEach((other) => other.removeAttribute('aria-sort'));
            header.setAttribute('aria-sort', ascending ? 'ascending' : 'descending');

            const rows = [...body.rows].sort((a, b) => (value(a) - value(b)) * (ascending ? 1 : -1));
            body.append(...rows);
        });
    });
});
