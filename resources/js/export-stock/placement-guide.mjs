export function initPlacementGuide({documentRef = globalThis.document, windowRef = globalThis.window} = {}) {
    const links = documentRef.querySelectorAll('[data-placement-guide-link]');
    if (!links.length) return;
    const select = documentRef.getElementById('group-destination-spare');
    const banner = documentRef.querySelector('[data-spare-guide-destination]');
    const update = () => {
        const value = select?.value ?? '';
        for (const link of links) {
            const url = new URL(link.dataset.placementGuideLink, documentRef.baseURI);
            if (value) url.searchParams.set('spare_destination', value);
            link.href = url.href;
        }
        if (banner) {
            const selected = select?.selectedOptions?.[0];
            banner.classList.toggle('is-unassigned', !value);
            banner.querySelector('[data-guide-pallet]').textContent = selected?.dataset.guidePallet || 'Pallet cadangan';
            banner.querySelector('[data-guide-location]').textContent = selected?.dataset.guideLocation || 'Belum dipilih';
        }
    };
    select?.addEventListener('change', update);
    windowRef?.addEventListener('pageshow', update);
    update();
}
