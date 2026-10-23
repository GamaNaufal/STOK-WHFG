@extends('shared.layouts.app')
@section('title', 'Hasil penanganan beberapa box')
@section('styles')<link rel="stylesheet" href="{{ asset('css/pages/expired-box.css') }}">@endsection
@section('content')
<div class="expired-page">
    <header class="expired-page-header"><a href="{{ route('expired-box.index') }}">← Daftar expired dan penanganan</a><h1 class="mt-3">{{ $records->count() }} box dalam pilihan</h1><p>Status terkini setiap box dan tindakan berikutnya.</p></header>
    @if(session('success'))<div class="alert alert-success" role="status">{{ session('success') }}</div>@endif
    <section class="expired-page-panel">
        <div class="table-responsive" tabindex="0" role="region" aria-label="Hasil penanganan massal"><table class="table expired-page-table"><thead><tr><th>Referensi</th><th>ID asal</th><th>ID pengganti</th><th>Status</th><th>Detail</th></tr></thead><tbody>@foreach($records as $record)<tr><td>{{ $record->reference }}</td><td>{{ $record->source_box_number }}</td><td>{{ $record->replacement_box_number ?? '—' }}</td><td>{{ $record->status_label }}</td><td><a href="{{ route('expired-box.show', $record) }}">Buka box {{ $record->source_box_number }}</a></td></tr>@endforeach</tbody></table></div>
        <div class="expired-bulk-toolbar">
            @foreach(['qa' => ['at_qa', 'Catat hasil QA'], 'packing' => ['awaiting_packing', 'Serahkan ke packing'], 'receive' => ['at_packing', 'Terima hasil packing']] as $action => [$state, $label])
                @php($ids = $records->where('status', $state)->pluck('id')->all())
                @if(count($ids))<a class="btn btn-primary" href="{{ route('expired-box.bulk.review', ['action' => $action, 'ids' => $ids]) }}">{{ $label }} · {{ count($ids) }} box</a>@endif
            @endforeach
            @php($printIds = $records->whereIn('status', ['awaiting_packing', 'at_packing', 'completed'])->pluck('id')->all())
            @if(count($printIds))<a class="btn btn-outline-primary" target="_blank" rel="noopener" href="{{ route('expired-box.bulk.review', ['action' => 'print', 'ids' => $printIds]) }}">Cetak pengantar · {{ count($printIds) }} box</a>@endif
        </div>
    </section>
</div>
@endsection
