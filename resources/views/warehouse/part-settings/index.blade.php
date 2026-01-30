@extends('shared.layouts.app')

@section('title', 'Master No Part - Warehouse FG Yamato')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h4 mb-0"><i class="bi bi-list-check"></i> Master No Part & Qty</h1>
        <a href="{{ route('part-settings.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-circle"></i> Tambah No Part
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="card shadow-sm border-0">
        <div class="card-body">
            <div class="row mb-3">
                <div class="col-md-4">
                    <label for="part-search" class="form-label">Cari No Part</label>
                    <input type="text" id="part-search" class="form-control" placeholder="Ketik No Part..." autocomplete="off">
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-striped align-middle">
                    <thead>
                        <tr>
                            <th>No Part</th>
                            <th>Qty Box (Tetap)</th>
                            <th class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="part-table-body">
                        @forelse($parts as $part)
                            <tr>
                                <td>{{ $part->part_number }}</td>
                                <td>{{ $part->qty_box }}</td>
                                <td class="text-end">
                                    <a href="{{ route('part-settings.edit', $part) }}" class="btn btn-sm btn-outline-primary">
                                        Edit
                                    </a>
                                    <button type="button"
                                        class="btn btn-sm btn-outline-danger js-delete-btn"
                                        data-delete-url="{{ route('part-settings.destroy', $part) }}"
                                        data-part-number="{{ $part->part_number }}">
                                        Hapus
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="text-center text-muted">Belum ada data.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div id="part-pagination">
                {{ $parts->links() }}
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="deletePartModal" tabindex="-1" aria-labelledby="deletePartModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deletePartModalLabel">Hapus No Part</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="mb-2">Anda yakin ingin menghapus No Part berikut?</p>
                <div class="fw-semibold" id="deletePartNumber">-</div>
                <div class="text-muted small mt-2">Tindakan ini tidak dapat dibatalkan.</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                <form id="deletePartForm" method="POST" class="d-inline">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger">Hapus</button>
                </form>
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
        const deleteModalEl = document.getElementById('deletePartModal');
        const deleteModal = deleteModalEl ? new bootstrap.Modal(deleteModalEl) : null;
        const deleteForm = document.getElementById('deletePartForm');
        const deletePartNumber = document.getElementById('deletePartNumber');

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
                tableBody.innerHTML = '<tr><td colspan="3" class="text-center text-muted">Tidak ada data.</td></tr>';
                return;
            }

            tableBody.innerHTML = items.map((item) => {
                const partNumber = escapeHtml(item.part_number ?? '');
                const qtyBox = escapeHtml(item.qty_box ?? '');
                const editUrl = `${baseUrl}/${item.id}/edit`;
                const deleteUrl = `${baseUrl}/${item.id}`;

                return `
                    <tr>
                        <td>${partNumber}</td>
                        <td>${qtyBox}</td>
                        <td class="text-end">
                            <a href="${editUrl}" class="btn btn-sm btn-outline-primary">Edit</a>
                            <button type="button"
                                class="btn btn-sm btn-outline-danger js-delete-btn"
                                data-delete-url="${deleteUrl}"
                                data-part-number="${partNumber}">
                                Hapus
                            </button>
                        </td>
                    </tr>
                `;
            }).join('');
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

            fetch(`${searchUrl}?q=${encodeURIComponent(query)}`)
                .then((response) => response.json())
                .then((data) => {
                    pagination.classList.add('d-none');
                    renderRows(data.data || []);
                    bindDeleteButtons();
                })
                .catch(() => {
                    tableBody.innerHTML = '<tr><td colspan="3" class="text-center text-muted">Gagal memuat data.</td></tr>';
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
