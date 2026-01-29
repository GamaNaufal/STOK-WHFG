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
                                    <form action="{{ route('part-settings.destroy', $part) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus data ini?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger">Hapus</button>
                                    </form>
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
@endsection

@section('scripts')
<script>
    (function () {
        const searchInput = document.getElementById('part-search');
        const tableBody = document.getElementById('part-table-body');
        const pagination = document.getElementById('part-pagination');
        const baseUrl = "{{ url('part-settings') }}";
        const searchUrl = "{{ route('part-settings.search') }}";
        const csrfToken = "{{ csrf_token() }}";

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
                            <form action="${deleteUrl}" method="POST" class="d-inline" onsubmit="return confirm('Hapus data ini?');">
                                <input type="hidden" name="_token" value="${csrfToken}">
                                <input type="hidden" name="_method" value="DELETE">
                                <button type="submit" class="btn btn-sm btn-outline-danger">Hapus</button>
                            </form>
                        </td>
                    </tr>
                `;
            }).join('');
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
                })
                .catch(() => {
                    tableBody.innerHTML = '<tr><td colspan="3" class="text-center text-muted">Gagal memuat data.</td></tr>';
                });
        };

        searchInput.addEventListener('input', (event) => {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(() => runSearch(event.target.value), 300);
        });
    })();
</script>
@endsection
