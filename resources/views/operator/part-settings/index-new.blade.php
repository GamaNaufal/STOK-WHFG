@extends('shared.layouts.app')

@section('title', 'Master No Part - Warehouse FG Yamato')

@section('content')
<div class="container-fluid">
    {{-- Page Header --}}
    <x-page-header 
        title="Master No Part & Qty" 
        subtitle="Kelola daftar nomor part dan jumlah box standar untuk penyimpanan"
        icon="list-check">
        <x-slot:action>
            <x-button href="{{ route('part-settings.create') }}" variant="light" icon="plus-circle">
                Tambah No Part
            </x-button>
        </x-slot:action>
    </x-page-header>

    {{-- Search Card --}}
    <div class="row">
        <div class="col-12">
            <x-card title="Cari & Filter Data" icon="search" borderColor="#0C7779" headerColor="#0C7779">
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
            </x-card>
        </div>
    </div>

    {{-- Data Table Card --}}
    <div class="row mt-4">
        <div class="col-12">
            <x-card title="Daftar Master No Part" icon="table" borderColor="#249E94" headerColor="#249E94" :noPadding="true">
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
                                            <x-button 
                                                href="{{ route('part-settings.edit', $part) }}" 
                                                variant="secondary" 
                                                size="sm" 
                                                icon="pencil">
                                                Edit
                                            </x-button>
                                            <x-button 
                                                variant="danger" 
                                                size="sm" 
                                                icon="trash"
                                                class="js-delete-btn"
                                                data-delete-url="{{ route('part-settings.destroy', $part) }}"
                                                data-part-number="{{ $part->part_number }}">
                                                Hapus
                                            </x-button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3">
                                        <x-empty-state 
                                            icon="inbox" 
                                            title="Belum ada data master no part"
                                            message="Mulai dengan menambahkan no part baru">
                                            <x-slot:action>
                                                <x-button href="{{ route('part-settings.create') }}" icon="plus-circle">
                                                    Tambah No Part
                                                </x-button>
                                            </x-slot:action>
                                        </x-empty-state>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                
                {{-- Pagination --}}
                <div id="part-pagination">
                    <x-pagination :paginator="$parts" />
                </div>
            </x-card>
        </div>
    </div>
</div>

{{-- Delete Modal --}}
<x-modal-delete 
    id="deletePartModal" 
    title="Hapus No Part"
    message="Anda yakin ingin menghapus No Part berikut?"
    itemName="-" />

@endsection

@section('scripts')
<script>
    (function () {
        const searchInput = document.getElementById('part-search');
        const tableBody = document.getElementById('part-table-body');
        const pagination = document.getElementById('part-pagination');
        const baseUrl = "{{ url('part-settings') }}";
        const searchUrl = "{{ route('part-settings.search') }}";
        const deleteModalEl = document.getElementById('deletePartModal');
        const deleteModal = deleteModalEl ? new bootstrap.Modal(deleteModalEl) : null;
        const deleteForm = document.getElementById('deletePartModalForm');
        const deletePartNumber = document.getElementById('deletePartModalItemName');

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
                const editUrl = \`\${baseUrl}/\${item.id}/edit\`;
                const deleteUrl = \`\${baseUrl}/\${item.id}\`;

                return \`
                    <tr style="border-bottom: 1px solid #e5e7eb;">
                        <td style="padding: 15px;">
                            <strong style="color: #0C7779; font-size: 15px;">\${partNumber}</strong>
                        </td>
                        <td style="padding: 15px;">
                            <span class="badge" style="background-color: #e0f2fe; color: #0369a1; font-size: 13px; padding: 6px 12px;">
                                \${qtyBox} Box
                            </span>
                        </td>
                        <td style="padding: 15px; text-align: center;">
                            <div class="d-flex gap-2 justify-content-center">
                                <a href="\${editUrl}" class="btn btn-sm" style="background-color: #249E94; color: white; border: none; padding: 6px 12px; border-radius: 6px;" title="Edit no part">
                                    <i class="bi bi-pencil"></i> Edit
                                </a>
                                <button type="button"
                                    class="btn btn-sm js-delete-btn"
                                    style="background-color: #ef4444; color: white; border: none; padding: 6px 12px; border-radius: 6px;"
                                    data-delete-url="\${deleteUrl}"
                                    data-part-number="\${partNumber}"
                                    title="Hapus no part">
                                    <i class="bi bi-trash"></i> Hapus
                                </button>
                            </div>
                        </td>
                    </tr>
                \`;
            }).join('');
            
            bindDeleteButtons();
        };

        const bindDeleteButtons = () => {
            const buttons = document.querySelectorAll('.js-delete-btn');
            buttons.forEach((btn) => {
                btn.addEventListener('click', () => {
                    if (!deleteModal || !deleteForm || !deletePartNumber) return;
                    const deleteUrl = btn.getAttribute('data-delete-url') || '#';
                    const partNumber = btn.getAttribute('data-part-number') || '-';
                    deleteForm.setAttribute('action', deleteUrl);
                    deletePartNumber.textContent = partNumber;
                    deleteModal.show();
                });
            });
        };

        const runSearch = (term) => {
            const query = term.trim();
            if (query.length === 0) {
                window.location.href = "{{ route('part-settings.index') }}";
                return;
            }

            fetch(\`\${searchUrl}?q=\${encodeURIComponent(query)}\`)
                .then((response) => response.json())
                .then((data) => {
                    pagination.classList.add('d-none');
                    renderRows(data.data || []);
                })
                .catch(() => {
                    tableBody.innerHTML = \`
                        <tr>
                            <td colspan="3" class="text-center py-5">
                                <i class="bi bi-exclamation-triangle" style="font-size: 3rem; color: #ef4444;"></i>
                                <h5 class="mt-3 text-danger">Gagal memuat data</h5>
                                <p class="text-muted small">Terjadi kesalahan saat mengambil data. Silakan coba lagi.</p>
                            </td>
                        </tr>
                    \`;
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
