document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('featureAccessForm');
    const boxes = [...form.querySelectorAll('.feature-checkbox:not(:disabled)')];
    const byKey = new Map(boxes.map(box => [box.value, box]));
    const initial = boxes.map(box => box.checked).join(',');
    let saving = false;
    const dirty = () => boxes.map(box => box.checked).join(',') !== initial;
    const updateStatus = () => {
        document.getElementById('accessChangeStatus').textContent = dirty()
            ? 'Ada perubahan yang belum disimpan.' : 'Belum ada perubahan.';
    };
    boxes.forEach(box => box.addEventListener('change', () => {
        if (box.checked) {
            JSON.parse(box.dataset.requires).forEach(key => { byKey.get(key).checked = true; });
        } else {
            boxes.forEach(child => {
                if (JSON.parse(child.dataset.requires).includes(box.value)) child.checked = false;
            });
        }
        updateStatus();
    }));
    document.getElementById('selectAllFeatures').addEventListener('click', () => {
        boxes.forEach(box => { box.checked = true; });
        updateStatus();
    });
    document.getElementById('clearAllFeatures').addEventListener('click', () => {
        boxes.forEach(box => { box.checked = false; });
        updateStatus();
    });
    form.addEventListener('submit', () => { saving = true; });
    window.addEventListener('beforeunload', event => {
        if (dirty() && !saving) {
            event.preventDefault();
            event.returnValue = '';
        }
    });
});
