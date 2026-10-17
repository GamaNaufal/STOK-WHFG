<section class="stock-toolbar mb-4" aria-label="Pencarian dan tampilan stok">
    <nav class="stock-toolbar__modes" aria-label="Tampilkan stok berdasarkan">
        @foreach(['part' => 'Part Number', 'box_id' => 'Box ID', 'pallet' => 'Pallet', 'not_full' => 'Box Not Full'] as $mode => $label)
            <a href="{{ route('stock-view.index', ['view_mode' => $mode, 'search' => $search, 'sort' => $sortMode]) }}"
               @if($viewMode === $mode) aria-current="page" @endif>{{ $label }}</a>
        @endforeach
    </nav>
    <form method="GET" action="{{ route('stock-view.index') }}" id="searchForm" class="stock-toolbar__form">
        <input type="hidden" name="view_mode" value="{{ $viewMode }}">
        <div class="stock-toolbar__search">
            <label for="searchInput">Cari stok</label>
            <input type="search" name="search" id="searchInput" class="form-control"
                   placeholder="Part, pallet, box, atau lokasi" value="{{ $search ?? '' }}"
                   autocomplete="off" aria-describedby="stockSearchHint">
            <div id="searchDropdown" class="stock-toolbar__suggestions" style="display: none;" aria-label="Saran pencarian"></div>
        </div>
        <div class="stock-toolbar__sort">
            <label for="stockSort">Urutkan</label>
            <select id="stockSort" name="sort" class="form-select">
                @foreach($sortOptions as $value => $label)
                    <option value="{{ $value }}" @selected($sortMode === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="btn btn-primary">Terapkan</button>
    </form>
    <p id="stockSearchHint" class="stock-toolbar__hint">Pilih saran atau tekan Terapkan untuk mencari dan mengurutkan stok.</p>
    @if($search !== null && $search !== '')
        <div class="stock-toolbar__active">
            <span>Pencarian aktif: <strong>{{ $search }}</strong></span>
            <a href="{{ route('stock-view.index', ['view_mode' => $viewMode, 'sort' => $sortMode]) }}">Hapus pencarian</a>
        </div>
    @endif
</section>
