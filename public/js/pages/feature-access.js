document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('featureAccessForm');
    if (!form) return;

    const boxes = [...form.querySelectorAll('.feature-checkbox:not(:disabled)')];
    const byKey = new Map(boxes.map((box) => [box.value, box]));
    const initial = new Map(boxes.map((box) => [box.value, box.checked]));
    const selectedCount = document.getElementById('accessSelectedCount');
    const changeStatus = document.getElementById('accessChangeStatus');
    const saveButton = document.getElementById('saveFeatureAccess');
    let saving = false;
    // Search tolerates pasted full-width text; permission keys and form values stay exact.
    const normalizeSearch = value => String(value ?? '').normalize('NFKC').toLocaleLowerCase('id-ID');
    const search = document.getElementById('accessPermissionSearch');
    const groups = [...form.querySelectorAll('[data-access-group]')];
    const rows = [...form.querySelectorAll('[data-permission-row]')];
    const searchStatus = document.getElementById('accessSearchStatus');
    const expandGroups = document.getElementById('expandAccessGroups');
    const collapseGroups = document.getElementById('collapseAccessGroups');
    const groupControls = document.getElementById('accessGroupControls');
    let searching = false, openBeforeSearch = new Map();
    // All groups stay open without JavaScript. Enhanced view starts with one chunk.
    groups.forEach((group, index) => { group.open = index === 0; });
    if (groupControls) groupControls.hidden = false;

    function filterGroups() {
        const terms = normalizeSearch(search?.value).trim().split(/\s+/).filter(Boolean);
        if (terms.length && !searching) openBeforeSearch = new Map(groups.map(group => [group, group.open]));
        rows.forEach(row => {
            const text = normalizeSearch(row.dataset.permissionSearch);
            row.hidden = !terms.every(term => text.includes(term));
        });
        groups.forEach(group => {
            group.hidden = ![...group.querySelectorAll('[data-permission-row]')].some(row => !row.hidden);
            if (terms.length) group.open = !group.hidden;
            else if (searching) group.open = openBeforeSearch.get(group) ?? group.open;
        });
        searching = terms.length > 0;
        if (expandGroups) expandGroups.disabled = searching;
        if (collapseGroups) collapseGroups.disabled = searching;
        const matches = rows.filter(row => !row.hidden).length;
        if (searchStatus) searchStatus.textContent = searching
            ? (matches ? `${matches} dari ${rows.length} menu atau tindakan cocok. Kelompok hasil dibuka otomatis.`
                : 'Tidak ada menu atau tindakan yang cocok. Coba kata lain atau hapus pencarian.')
            : `${rows.length} menu atau tindakan dalam ${groups.length} kelompok. Buka kelompok yang ingin diperiksa.`;
    }
    search?.addEventListener('input', filterGroups);
    expandGroups?.addEventListener('click', () => {
        if (!searching) groups.forEach(group => { group.open = true; });
    });
    collapseGroups?.addEventListener('click', () => {
        if (!searching) groups.forEach(group => { group.open = false; });
    });

    const dependencies = (box) => JSON.parse(box.dataset.requires || '[]');
    const changedCount = () => boxes.filter((box) => box.checked !== initial.get(box.value)).length;
    const checkedCount = () => boxes.filter((box) => box.checked).length;

    function updateStatus() {
        const changes = changedCount();
        selectedCount.textContent = `${checkedCount()} dari ${boxes.length} fitur dipilih`;
        changeStatus.textContent = changes ? `${changes} pilihan belum disimpan.` : 'Belum ada perubahan.';
        changeStatus.classList.toggle('is-dirty', changes > 0);

        form.querySelectorAll('[data-access-group]').forEach((group) => {
            const count = group.querySelector('[data-group-count]');
            const groupBoxes = [...group.querySelectorAll('.feature-checkbox:not(:disabled)')];
            count.textContent = groupBoxes.length
                ? `${groupBoxes.filter((box) => box.checked).length} dari ${groupBoxes.length} dipilih`
                : 'Khusus admin';
        });
    }

    function checkDependencies(box) {
        dependencies(box).forEach((key) => {
            const required = byKey.get(key);
            if (required && !required.checked) {
                required.checked = true;
                checkDependencies(required);
            }
        });
    }

    function clearDependents(key) {
        boxes.forEach((box) => {
            if (box.checked && dependencies(box).includes(key)) {
                box.checked = false;
                clearDependents(box.value);
            }
        });
    }

    boxes.forEach((box) => box.addEventListener('change', () => {
        if (box.checked) checkDependencies(box);
        else clearDependents(box.value);
        updateStatus();
    }));

    document.getElementById('selectAllFeatures').addEventListener('click', () => {
        boxes.forEach((box) => { box.checked = true; });
        updateStatus();
    });
    document.getElementById('clearAllFeatures').addEventListener('click', () => {
        boxes.forEach((box) => { box.checked = false; });
        updateStatus();
    });

    form.addEventListener('submit', (event) => {
        if (saving) {
            event.preventDefault();
            return;
        }
        if (!changedCount()) {
            saving = true;
            return;
        }

        event.preventDefault();
        const roleLabel = document.createElement('span');
        roleLabel.textContent = form.dataset.roleLabel || 'peran ini';
        const warningItems = checkedCount() === 0
            ? ['Semua fitur yang dapat diatur untuk peran ini akan dinonaktifkan.']
            : [`${checkedCount()} dari ${boxes.length} fitur akan tersedia untuk peran ini.`];
        warningItems.push('Fitur yang tidak dipilih tidak dapat digunakan oleh akun dengan peran ini.');
        warningItems.push('Perubahan berlaku pada permintaan berikutnya. Dashboard dan profil tetap tersedia.');

        WarehouseAlert.confirm({
            title: 'Simpan perubahan hak akses?',
            message: `Pengaturan untuk <strong>${roleLabel.innerHTML}</strong> akan menggantikan daftar izin sebelumnya.`,
            warningItems,
            confirmText: 'Ya, simpan akses',
            onConfirm: () => {
                if (saving) return;
                saving = true;
                saveButton.disabled = true;
                saveButton.textContent = 'Menyimpan…';
                form.submit();
            }
        });
    });

    window.addEventListener('beforeunload', (event) => {
        if (changedCount() && !saving) {
            event.preventDefault();
            event.returnValue = '';
        }
    });

    updateStatus();
    filterGroups();
});
