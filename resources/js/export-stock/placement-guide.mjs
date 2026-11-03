import {readSparePlacement} from './spare-placement.mjs';

export function initPlacementGuide({documentRef = globalThis.document, windowRef = globalThis.window} = {}) {
    const links = documentRef.querySelectorAll('[data-placement-guide-link]');
    if (!links.length) return;
    const blocks = [...documentRef.querySelectorAll('[data-spare-placement]')];
    const printForm = documentRef.querySelector('[data-spare-print-form]');
    if (blocks.length && printForm) {
        for (const link of links) link.addEventListener('click', event => {
            if (event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
            event.preventDefault();
            try {
                const rows = blocks.flatMap(block => readSparePlacement(block, {requireDestination:false}));
                printForm.querySelector('[name="spare_placements_json"]').value = JSON.stringify(rows.map(({id,destination})=>({id,destination:destination||null})));
                printForm.requestSubmit();
            } catch (error) {
                const block = blocks.find(item => { try { readSparePlacement(item,{requireDestination:false}); return false; } catch { return true; } });
                const status = block?.querySelector('[data-spare-allocation-status]');
                if (status) { status.textContent = error.message+' Perbaiki pembagian sebelum mencetak.'; status.tabIndex=-1; status.focus(); }
            }
        });
        return;
    }
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
