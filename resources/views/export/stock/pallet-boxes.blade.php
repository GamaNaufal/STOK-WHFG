<p class="receiving-table-note">{{ $boxes->total() }} box sesuai filter · urutan pertama diterima (FIFO).</p>
@include('export.stock.table', ['showPhysical' => false])
@if($boxes->hasPages())<div class="plan-panel-body">{{ $boxes->links('pagination::bootstrap-5') }}</div>@endif
