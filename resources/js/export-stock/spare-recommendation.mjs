export function spareRecommendationState(previous, quantity, otherIncoming = 0) {
    const fmt = value => Number(value).toLocaleString('id-ID');
    const available = previous.limit == null ? quantity : Math.max(0, previous.limit - previous.current - otherIncoming);
    const count = Math.min(quantity, available), overflow = quantity - count;
    const capacity = previous.limit == null ? `Isi ${fmt(previous.current)} box, tanpa batas box.`
        : `Isi ${fmt(previous.current)} dari maksimal ${fmt(previous.limit)} box, termasuk seluruh nomor part.`;
    const other = otherIncoming > 0 ? ` ${fmt(otherIncoming)} box dari kelompok lain pada transaksi ini juga menuju pallet tersebut.` : '';
    const reason = previous.required ? `Seluruh tambahan ${fmt(quantity)} box masih muat. Wajib ditempatkan pada pallet ini, tujuan tidak dapat diganti. Konfirmasikan setelah peletakan fisik.`
        : overflow === 0 ? `Seluruh tambahan ${fmt(quantity)} box masih muat, lokasi ini direkomendasikan.`
        : count === 0 ? `Pallet sebelumnya sudah penuh${otherIncoming > 0 ? ' setelah memperhitungkan pilihan kelompok lain' : ''}. Tambahan ${fmt(quantity)} box tidak dapat ditempatkan di sini. Pilih lokasi pallet baru atau tujuan lain yang tersedia.`
        : `Ruang tersisa ${fmt(count)} box. Tambahan ${fmt(quantity)} box tidak seluruhnya muat, ${fmt(overflow)} box melebihi batas. Isi sisa ruang dan pilih tujuan untuk ${fmt(overflow)} box lainnya, atau gunakan pallet baru untuk seluruh tambahan.`;
    return {count, overflow, message: `${previous.number} di ${previous.location}. ${capacity}${other} ${reason}`};
}

export function assertSpareDestination(previous, allocations) {
    if (previous?.required && allocations.some(row => row.destination !== previous.destination)) {
        throw new Error(`Wajib ditempatkan pada ${previous.number} di ${previous.location}, seluruh tambahan masih muat.`);
    }
}
