export function createOpnameQueue({send, acknowledge, state}) {
    const pending = [];
    let running = false, paused = false, error = '';
    const publish = () => state({pending: pending.map(item => item.box_number), running, paused, error});
    const drain = async () => {
        if (running || paused) return;
        running = true;
        publish();
        try {
            while (pending.length && !paused) {
                const result = await send(pending[0]);
                acknowledge(result);
                pending.shift();
                error = '';
                publish();
            }
        } catch (failure) {
            paused = true;
            error = failure.message || 'Koneksi bermasalah.';
        } finally {
            running = false;
            publish();
        }
    };
    return {
        enqueue(item) { pending.push(item); publish(); return drain(); },
        retry() { paused = false; error = ''; return drain(); },
        size() { return pending.length; },
    };
}
