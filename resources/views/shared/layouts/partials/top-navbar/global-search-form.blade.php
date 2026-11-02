<form method="GET" action="{{ route('dashboard') }}" role="search" class="top-search-form" id="globalSearchForm" autocomplete="off">
    <div class="input-group">
        <span class="input-group-text"><i class="bi bi-search" aria-hidden="true"></i></span>
        <input type="text" name="q" id="globalSearchInput" class="form-control" value="" placeholder="Cari menu atau fitur…" aria-label="Cari menu atau fitur" role="combobox" aria-autocomplete="list" aria-controls="globalSearchResults" aria-expanded="false" spellcheck="false">
        <button type="button" id="globalSearchClear" aria-label="Hapus pencarian" title="Hapus pencarian" hidden><i class="bi bi-x-lg" aria-hidden="true"></i></button>
    </div>
    <div id="globalSearchDropdown" class="global-search-dropdown" hidden>
        <p id="globalSearchSummary" class="global-search-summary" role="status" aria-live="polite"></p>
        <div id="globalSearchResults" role="listbox" aria-label="Saran menu dan fitur"></div>
        <p id="globalSearchEmpty" class="global-search-empty" hidden>Fitur tidak ditemukan. Coba kata lain.</p>
        <button type="button" id="globalSearchShowAll" class="global-search-show-all" hidden>Lihat semua hasil</button>
        <p class="global-search-hint">↑ ↓ Pilih · Enter Buka · Esc Tutup</p>
    </div>
</form>
