@extends('shared.layouts.app')

@section('title', 'Master No Part - Warehouse FG Yamato')

@section('content')
<div class="container-fluid">
    <!-- Modern Header Banner -->
    <div class="row mb-4">
        <div class="col-12">
            <div style="background: linear-gradient(135deg, #0C7779 0%, #249E94 100%); 
                        color: white; 
                        padding: 40px 30px; 
                        border-radius: 12px; 
                        box-shadow: 0 8px 24px rgba(12, 119, 121, 0.15);">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h1 class="h2" style="margin: 0 0 10px 0; font-weight: 700;">
                            <i class="bi bi-list-check"></i> Master No Part & Qty
                        </h1>
                        <p style="margin: 0; opacity: 0.95; font-size: 15px;">
                            Kelola daftar nomor part dan jumlah box standar untuk penyimpanan
                        </p>
                    </div>
                    <a href="{{ route('part-settings.create') }}" class="btn" style="background-color: white; color: #0C7779; border: none; padding: 10px 20px; font-weight: 600; border-radius: 8px; transition: all 0.3s ease;" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 4px 12px rgba(0,0,0,0.15)'" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none'">
                        <i class="bi bi-plus-circle"></i> Tambah No Part
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content Card -->
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm border-0" style="border-left: 4px solid #0C7779; border-radius: 12px; overflow: hidden;">
                <div class="card-header text-white" style="background-color: #0C7779; border: none;">
                    <i class="bi bi-search"></i> Cari & Filter Data
                </div>
                <div class="card-body p-4" style="background-color: #f8f9fa;">
                    <div class="row">
                        <div class="col-md-6">
                            <label for="part-search" class="form-label fw-bold" style="color: #0C7779;">
                                <i class="bi bi-search"></i> Cari No Part
                            </label>
                            <div class="input-group input-group-lg">
                                <span class="input-group-text" style="background: white; color: #0C7779; border: 2px solid #e5e7eb;">
                                    <i class="bi bi-barcode"></i>
                                </span>
                                <input type="text" id="part-search" class="form-control" style="border: 2px solid #e5e7eb;" placeholder="Ketik No Part..." autocomplete="off">
                            </div>
                            <small class="form-text text-muted d-block mt-2">
                                <i class="bi bi-info-circle"></i> Cari berdasarkan nomor part - hasil akan difilter secara real-time
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Data Table Card -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card shadow-sm border-0" style="border-left: 4px solid #249E94; border-radius: 12px; overflow: hidden;">
                <div class="card-header text-white" style="background-color: #249E94; border: none;">
                    <i class="bi bi-table"></i> Daftar Master No Part
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead style="background-color: #f5f7fa; color: #0C7779;">
                                <tr>
                                    <th style="width: 40%; padding: 15px;">
                                        <i class="bi bi-barcode"></i> No Part
                                    </th>
                                    <th style="width: 30%; padding: 15px;">
                                        <i class="bi bi-box"></i> Qty Box Standar
                                    </th>
                                    <th style="width: 30%; padding: 15px; text-align: center;">
                                        <i class="bi bi-gear"></i> Aksi
                                    </th>
                                </tr>
                            </thead>
                            <tbody id="part-table-body">
                                @forelse($parts as $part)
                                    <tr style="border-bottom: 1px solid #e5e7eb;">
                                        <td style="padding: 15px;">
                                            <strong style="color: #0C7779; font-size: 15px;">{{ $part->part_number }}</strong>
                                        </td>
                                        <td style="padding: 15px;">
                                            <span class="badge" style="background-color: #e0f2fe; color: #0369a1; font-size: 13px; padding: 6px 12px;">
                                                {{ $part->qty_box }} Box
                                            </span>
                                        </td>
                                        <td style="padding: 15px; text-align: center;">
                                            <div class="d-flex gap-2 justify-content-center">
                                                <a href="{{ route('part-settings.edit', $part) }}" class="btn btn-sm" style="background-color: #249E94; color: white; border: none; padding: 6px 12px; border-radius: 6px; transition: all 0.3s ease;" onmouseover="this.style.backgroundColor='#0C7779'" onmouseout="this.style.backgroundColor='#249E94'" title="Edit no part">
                                                    <i class="bi bi-pencil"></i> Edit
                                                </a>
                                                <button type="button"
                                                    class="btn btn-sm js-delete-btn"
                                                    style="background-color: #ef4444; color: white; border: none; padding: 6px 12px; border-radius: 6px; transition: all 0.3s ease;"
                                                    onmouseover="this.style.backgroundColor='#dc2626'"
                                                    onmouseout="this.style.backgroundColor='#ef4444'"
                                                    data-delete-url="{{ route('part-settings.destroy', $part) }}"
                                                    data-part-number="{{ $part->part_number }}"
                                                    title="Hapus no part">
                                                    <i class="bi bi-trash"></i> Hapus
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="text-center py-5">
                                            <i class="bi bi-inbox" style="font-size: 3rem; color: #ccc;"></i>
                                            <h5 class="mt-3 text-muted">Belum ada data master no part</h5>
                                            <p class="text-muted small">
                                                <a href="{{ route('part-settings.create') }}" style="color: #0C7779; text-decoration: none;">Tambah no part baru</a>
                                            </p>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div id="part-pagination" class="p-4" style="background-color: #f8f9fa; border-top: 1px solid #e5e7eb;">
                        <nav aria-label="Pagination Navigation" class="d-flex justify-content-between align-items-center">
                            <!-- Previous Button -->
                            <div>
                                @if ($parts->onFirstPage())
                                    <button class="btn btn-sm" style="background-color: #e5e7eb; color: #9ca3af; border: none; padding: 8px 16px; border-radius: 8px; cursor: not-allowed;">
                                        <i class="bi bi-chevron-left"></i> Previous
                                    </button>
                                @else
                                    <a href="{{ $parts->previousPageUrl() }}" class="btn btn-sm" style="background-color: #0C7779; color: white; border: none; padding: 8px 16px; border-radius: 8px; transition: all 0.3s ease;" onmouseover="this.style.backgroundColor='#249E94'" onmouseout="this.style.backgroundColor='#0C7779'">
                                        <i class="bi bi-chevron-left"></i> Previous
                                    </a>
                                @endif
                            </div>

                            <!-- Page Info -->
                            <div class="text-center">
                                <small class="text-muted d-block" style="font-size: 13px;">
                                    <strong>Halaman {{ $parts->currentPage() }}</strong> dari <strong>{{ $parts->lastPage() }}</strong>
                                </small>
                                <small class="text-muted d-block" style="font-size: 12px;">
                                    Menampilkan <strong>{{ $parts->count() }}</strong> dari <strong>{{ $parts->total() }}</strong> hasil
                                </small>
                            </div>

                            <!-- Next Button -->
                            <div>
                                @if ($parts->hasMorePages())
                                    <a href="{{ $parts->nextPageUrl() }}" class="btn btn-sm" style="background-color: #0C7779; color: white; border: none; padding: 8px 16px; border-radius: 8px; transition: all 0.3s ease;" onmouseover="this.style.backgroundColor='#249E94'" onmouseout="this.style.backgroundColor='#0C7779'">
                                        Next <i class="bi bi-chevron-right"></i>
                                    </a>
                                @else
                                    <button class="btn btn-sm" style="background-color: #e5e7eb; color: #9ca3af; border: none; padding: 8px 16px; border-radius: 8px; cursor: not-allowed;">
                                        Next <i class="bi bi-chevron-right"></i>
                                    </button>
                                @endif
                            </div>
                        </nav>

                        <!-- Page Number Links -->
                        @if($parts->lastPage() > 1)
                        <div class="mt-3 d-flex justify-content-center gap-2">
                            @foreach ($parts->getUrlRange(1, $parts->lastPage()) as $page => $url)
                                @if ($page == $parts->currentPage())
                                    <span class="btn btn-sm" style="background-color: #0C7779; color: white; border: none; min-width: 40px; padding: 6px 10px; border-radius: 6px;">
                                        {{ $page }}
                                    </span>
                                @else
                                    <a href="{{ $url }}" class="btn btn-sm" style="background-color: #f3f4f6; color: #0C7779; border: 1px solid #e5e7eb; min-width: 40px; padding: 6px 10px; border-radius: 6px; transition: all 0.3s ease;" onmouseover="this.style.backgroundColor='#e5e7eb'" onmouseout="this.style.backgroundColor='#f3f4f6'">
                                        {{ $page }}
                                    </a>
                                @endif
                            @endforeach
                        </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    (function () {
        const searchInput = document.getElementById('part-search');
        const tableBody = document.getElementById('part-table-body');
        const pagination = document.getElementById('part-pagination');
        const baseUrl = "{{ url('part-settings') }}";
        const searchUrl = "{{ route('part-settings.search') }}";

        let searchTimer = null;

        const escapeHtml = (value) => {
            return String(value)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        };

        const renderRows = (items) => {
            if (!items.length) {
                tableBody.innerHTML = `
                    <tr>
                        <td colspan="3" class="text-center py-5">
                            <i class="bi bi-inbox" style="font-size: 3rem; color: #ccc;"></i>
                            <h5 class="mt-3 text-muted">Tidak ada data ditemukan</h5>
                            <p class="text-muted small">Coba kata kunci pencarian lain</p>
                        </td>
                    </tr>
                `;
                return;
            }

            tableBody.innerHTML = items.map((item) => {
                const partNumber = escapeHtml(item.part_number ?? '');
                const qtyBox = escapeHtml(item.qty_box ?? '');
                const editUrl = `${baseUrl}/${item.id}/edit`;
                const deleteUrl = `${baseUrl}/${item.id}`;

                return `
                    <tr style="border-bottom: 1px solid #e5e7eb;">
                        <td style="padding: 15px;">
                            <strong style="color: #0C7779; font-size: 15px;">${partNumber}</strong>
                        </td>
                        <td style="padding: 15px;">
                            <span class="badge" style="background-color: #e0f2fe; color: #0369a1; font-size: 13px; padding: 6px 12px;">
                                ${qtyBox} Box
                            </span>
                        </td>
                        <td style="padding: 15px; text-align: center;">
                            <div class="d-flex gap-2 justify-content-center">
                                <a href="${editUrl}" class="btn btn-sm" style="background-color: #249E94; color: white; border: none; padding: 6px 12px; border-radius: 6px;" title="Edit no part">
                                    <i class="bi bi-pencil"></i> Edit
                                </a>
                                <button type="button"
                                    class="btn btn-sm js-delete-btn"
                                    style="background-color: #ef4444; color: white; border: none; padding: 6px 12px; border-radius: 6px;"
                                    data-delete-url="${deleteUrl}"
                                    data-part-number="${partNumber}"
                                    title="Hapus no part">
                                    <i class="bi bi-trash"></i> Hapus
                                </button>
                            </div>
                        </td>
                    </tr>
                `;
            }).join('');
        };

        const bindDeleteButtons = () => {
            const buttons = document.querySelectorAll('.js-delete-btn');
            buttons.forEach((btn) => {
                btn.addEventListener('click', () => {
                    const deleteUrl = btn.getAttribute('data-delete-url') || '#';
                    const partNumber = btn.getAttribute('data-part-number') || '-';
                    
                    WarehouseAlert.delete({
                        title: 'Hapus No Part?',
                        itemName: `No Part <strong>${partNumber}</strong>`,
                        warningItems: [
                            'Tindakan ini <strong>tidak dapat dibatalkan</strong>',
                            'Data akan dihapus secara permanen'
                        ],
                        confirmText: 'Ya, Hapus',
                        onConfirm: () => {
                            const form = document.createElement('form');
                            form.method = 'POST';
                            form.action = deleteUrl;
                            form.innerHTML = `
                                <input type="hidden" name="_token" value="{{ csrf_token() }}">
                                <input type="hidden" name="_method" value="DELETE">
                            `;
                            document.body.appendChild(form);
                            form.submit();
                        }
                    });
                });
            });
        };

        const runSearch = (term) => {
            const query = term.trim();
            if (query.length === 0) {
                window.location.href = "{{ route('part-settings.index') }}";
                return;
            }

            fetch(`${searchUrl}?q=${encodeURIComponent(query)}`)
                .then((response) => response.json())
                .then((data) => {
                    pagination.classList.add('d-none');
                    renderRows(data.data || []);
                    bindDeleteButtons();
                })
                .catch(() => {
                    tableBody.innerHTML = `
                        <tr>
                            <td colspan="3" class="text-center py-5">
                                <i class="bi bi-exclamation-triangle" style="font-size: 3rem; color: #ef4444;"></i>
                                <h5 class="mt-3 text-danger">Gagal memuat data</h5>
                                <p class="text-muted small">Terjadi kesalahan saat mengambil data. Silakan coba lagi.</p>
                            </td>
                        </tr>
                    `;
                });
        };

        searchInput.addEventListener('input', (event) => {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(() => runSearch(event.target.value), 300);
        });

        bindDeleteButtons();
    })();
</script>
@endsection
