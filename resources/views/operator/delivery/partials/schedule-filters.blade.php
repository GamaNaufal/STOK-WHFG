<form id="delivery-schedule-filters" class="delivery-schedule-filters" role="search" aria-label="Filter jadwal delivery aktif" hidden>
    <div class="delivery-schedule-filter-grid">
        <div>
            <label for="delivery-schedule-search">Cari jadwal</label>
            <input id="delivery-schedule-search" class="form-control" type="search" placeholder="Nomor order, customer, atau part" autocomplete="off" aria-describedby="delivery-schedule-filter-help" aria-controls="delivery-schedule-results">
        </div>
        <div>
            <label for="delivery-schedule-timing">Waktu pengiriman</label>
            <select id="delivery-schedule-timing" class="form-select" aria-controls="delivery-schedule-results">
                <option value="all">Semua jadwal</option>
                <option value="overdue">Terlambat</option>
                <option value="today">Hari ini</option>
                <option value="upcoming">Mendatang</option>
            </select>
        </div>
        <button type="reset" class="btn btn-outline-secondary">Reset filter</button>
    </div>
    <div class="delivery-schedule-filter-meta">
        <p id="delivery-schedule-filter-help">Filter langsung pada jadwal aktif. Pesanan selesai dan riwayat tetap ditampilkan.</p>
        <p id="delivery-schedule-filter-count" role="status" aria-live="polite" aria-atomic="true"></p>
    </div>
</form>
<div id="delivery-schedule-no-results" class="delivery-schedule-no-results" hidden>
    <strong>Tidak ada jadwal yang cocok.</strong>
    <p>Ubah kata pencarian atau gunakan Reset filter untuk melihat semua jadwal aktif.</p>
</div>
