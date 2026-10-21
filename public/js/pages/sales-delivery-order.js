document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('sales-order-form');
    if (!form) return;

    const container = document.getElementById('items-container');
    const template = document.getElementById('sales-order-item-template');
    const addButton = document.getElementById('add-item-btn');
    const countLabel = document.getElementById('sales-order-item-count');
    const totalLabel = document.getElementById('sales-order-pcs-total');
    const itemsError = document.getElementById('sales-order-items-error');
    const formError = document.getElementById('sales-order-form-error');
    const submitButton = form.querySelector('[type="submit"]');
    const customer = form.querySelector('[name="customer_name"]');
    const date = form.querySelector('[name="delivery_date"]');
    const isCorrection = form.dataset.salesOrderForm === 'edit';
    const numberFormat = new Intl.NumberFormat('id-ID');
    let nextIndex = Number.parseInt(container.dataset.nextIndex || '0', 10);
    let submitting = false;

    // Select2 hides its native select, so validation is handled on the visible form.
    // The server remains authoritative when JavaScript is unavailable.
    form.noValidate = true;

    function rows() {
        return [...container.querySelectorAll('[data-item-row]')];
    }

    function escapeHtml(value) {
        const element = document.createElement('span');
        element.textContent = value;
        return element.innerHTML;
    }

    function showItemsError(message) {
        itemsError.textContent = message;
        itemsError.hidden = !message;
    }

    function showFormError(message) {
        formError.textContent = message;
        formError.hidden = !message;
    }

    function clearFieldError(field) {
        field.classList.remove('is-invalid');
        field.setAttribute('aria-invalid', 'false');
        const serverError = field.closest('.sales-order-field')?.querySelector('.sales-order-inline-error');
        if (serverError) serverError.hidden = true;
    }

    function initPartSelects(context) {
        if (!window.jQuery?.fn?.select2) return;
        context.querySelectorAll('.part-select').forEach((select) => {
            if (select.dataset.enhanced) return;
            window.jQuery(select).select2({
                width: '100%',
                placeholder: 'Pilih No Part',
                allowClear: true,
                minimumResultsForSearch: 0,
            });
            select.dataset.enhanced = 'true';
            window.jQuery(select).on('change.salesOrder', () => {
                clearFieldError(select);
                showFormError('');
                showItemsError(duplicateParts()
                    ? 'Nomor part yang sama dipilih lebih dari sekali. Gabungkan PCS dalam satu item.' : '');
                updateSummary();
            });
        });
    }

    function updateSummary() {
        const itemRows = rows();
        let total = 0;
        let hasQuantity = false;
        itemRows.forEach((row, index) => {
            row.querySelector('[data-item-number]').textContent = `Item ${index + 1}`;
            row.querySelector('.remove-item').setAttribute('aria-label', `Hapus item ${index + 1}`);
            row.querySelector('.remove-item').disabled = itemRows.length === 1;
            const rawQuantity = row.querySelector('[name$="[quantity]"]').value;
            const quantity = Number(rawQuantity);
            if (/^[0-9]+$/.test(rawQuantity) && Number.isSafeInteger(quantity) && quantity > 0) {
                total += quantity;
                hasQuantity = true;
            }
        });
        countLabel.textContent = `${itemRows.length} item`;
        totalLabel.textContent = hasQuantity ? `${numberFormat.format(total)} PCS diminta` : '— PCS diminta';
    }

    function duplicateParts() {
        const firstRowByPart = new Map();
        const duplicates = new Set();
        rows().forEach((row) => {
            const part = row.querySelector('.part-select').value;
            if (!part) return;
            if (firstRowByPart.has(part)) {
                duplicates.add(row);
                duplicates.add(firstRowByPart.get(part));
            } else {
                firstRowByPart.set(part, row);
            }
        });
        rows().forEach((row) => row.classList.toggle('has-duplicate', duplicates.has(row)));
        return duplicates.size > 0;
    }

    function validateOrder() {
        if (!customer.value.trim()) {
            customer.classList.add('is-invalid');
            customer.setAttribute('aria-invalid', 'true');
            customer.focus();
            return 'Isi nama pelanggan sebelum mengirim pesanan.';
        }
        if (!date.value) {
            date.classList.add('is-invalid');
            date.setAttribute('aria-invalid', 'true');
            date.focus();
            return 'Pilih tanggal delivery sebelum mengirim pesanan.';
        }
        if (!rows().length) return 'Tambahkan setidaknya satu item pesanan.';

        for (const row of rows()) {
            const select = row.querySelector('.part-select');
            const quantity = row.querySelector('[name$="[quantity]"]');
            if (!select.value) {
                select.classList.add('is-invalid');
                select.setAttribute('aria-invalid', 'true');
                if (select.dataset.enhanced) window.jQuery(select).select2('open');
                else select.focus();
                return 'Pilih nomor part untuk setiap item.';
            }
            if (!/^[0-9]+$/.test(quantity.value) || !Number.isSafeInteger(Number(quantity.value)) || Number(quantity.value) < 1) {
                quantity.classList.add('is-invalid');
                quantity.setAttribute('aria-invalid', 'true');
                quantity.focus();
                return 'Jumlah setiap item harus berupa PCS bulat minimal 1.';
            }
        }

        if (duplicateParts()) return 'Nomor part yang sama dipilih lebih dari sekali. Gabungkan PCS dalam satu item.';
        return '';
    }

    addButton.addEventListener('click', () => {
        const row = template.content.firstElementChild.cloneNode(true);
        const select = row.querySelector('.part-select');
        const quantity = row.querySelector('[name$="[quantity]"]');
        select.name = `items[${nextIndex}][part_number]`;
        select.id = `sales-part-${nextIndex}`;
        row.querySelector('label[for="sales-part-template"]').htmlFor = select.id;
        quantity.name = `items[${nextIndex}][quantity]`;
        quantity.id = `sales-quantity-${nextIndex}`;
        row.querySelector('label[for="sales-quantity-template"]').htmlFor = quantity.id;
        nextIndex += 1;
        container.appendChild(row);
        initPartSelects(row);
        showItemsError('');
        updateSummary();
        if (select.dataset.enhanced) window.jQuery(select).select2('open');
        else select.focus();
    });

    container.addEventListener('click', (event) => {
        const button = event.target.closest('.remove-item');
        if (!button || rows().length <= 1) return;
        const row = button.closest('[data-item-row]');
        const select = row.querySelector('.part-select');
        if (select.dataset.enhanced) {
            window.jQuery(select).off('change.salesOrder').select2('destroy');
        }
        row.remove();
        duplicateParts();
        showItemsError('');
        updateSummary();
    });

    container.addEventListener('change', (event) => {
        if (event.target.matches('.part-select')) {
            clearFieldError(event.target);
            showFormError('');
            showItemsError(duplicateParts()
                ? 'Nomor part yang sama dipilih lebih dari sekali. Gabungkan PCS dalam satu item.' : '');
        }
        updateSummary();
    });
    container.addEventListener('input', (event) => {
        if (event.target.matches('[name$="[quantity]"]')) {
            clearFieldError(event.target);
            showFormError('');
            updateSummary();
        }
    });

    [customer, date].forEach((field) => {
        field.addEventListener('input', () => {
            clearFieldError(field);
            showFormError('');
        });
    });

    form.addEventListener('submit', (event) => {
        event.preventDefault();
        if (submitting) return;
        const error = validateOrder();
        if (error) {
            showFormError(error);
            if (error.includes('nomor part') || error.includes('Nomor part') || error.includes('PCS')) showItemsError(error);
            return;
        }
        showFormError('');
        showItemsError('');

        WarehouseAlert.info({
            title: isCorrection ? 'Kirim ulang koreksi?' : 'Kirim pesanan ke PPC?',
            message: isCorrection
                ? 'Perubahan pesanan akan menggantikan item sebelumnya dan dikirim untuk review ulang PPC.'
                : 'Pesanan baru akan dikirim untuk review PPC.',
            details: {
                'Pelanggan': escapeHtml(customer.value.trim()),
                'Tanggal': escapeHtml(date.value),
                'Item': `${rows().length} item`,
                'Total': escapeHtml(totalLabel.textContent),
            },
            infoText: 'Status pesanan menjadi menunggu keputusan PPC.',
            confirmText: isCorrection ? 'Ya, kirim ulang' : 'Ya, kirim pesanan',
            onConfirm: () => {
                if (submitting) return;
                submitting = true;
                submitButton.disabled = true;
                submitButton.textContent = 'Mengirim…';
                form.submit();
            },
        });
    });

    initPartSelects(container);
    updateSummary();
    duplicateParts();
});
