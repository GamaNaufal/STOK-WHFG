@if($stockNotice)
    <div class="alert alert-info d-flex flex-wrap gap-2 justify-content-between align-items-center" role="status"><span>{{ $stockReviewRequired ? 'Konfirmasi hasil pencarian stok FIFO untuk memasukkan tugas ke antrean.' : 'Hasil alokasi stok FIFO tersimpan dan dapat ditinjau kembali.' }}</span><a class="btn btn-outline-primary" href="{{ route('export.pallet-plans.show', ['deliveryPlan' => $delivery->id, 'review_stock' => 1]) }}">Tinjau alokasi FIFO</a></div>
    @if($stockReviewOpen)
    <div class="modal fade" id="stock-allocation-modal" tabindex="-1" aria-labelledby="stock-allocation-title" aria-hidden="true" data-bs-backdrop="{{ $stockReviewRequired ? 'static' : 'true' }}" data-bs-keyboard="{{ $stockReviewRequired ? 'false' : 'true' }}">
        <div class="modal-dialog modal-xl modal-dialog-scrollable"><div class="modal-content">
            <div class="modal-header"><div><h2 class="modal-title fs-5" id="stock-allocation-title">Stok FIFO untuk rencana pallet</h2><p class="plan-help mb-0 mt-1">{{ $delivery->plan_code ?? 'Rencana delivery' }} · {{ $delivery->customer_name }} · Hasil finalisasi revisi {{ $stockNotice->plan_revision }}</p></div></div>
            <div class="modal-body">
                @unless($stockReviewRequired)<p class="alert alert-info">{{ $stockNotice->acknowledged_at ? 'Hasil alokasi telah dikonfirmasi pada '.$stockNotice->acknowledged_at->format('d/m/Y H:i').'.' : 'Hasil finalisasi revisi sebelumnya.' }} Tampilan ini untuk tinjauan; tidak membuat reservasi atau tugas baru.</p>@endunless
                @if($stockAssignments->total())
                    <p><strong>{{ number_format($stockAssignments->total(), 0, ',', '.') }} box {{ $stockReviewRequired ? 'stok tersedia sudah direservasi' : 'tercatat dalam alokasi FIFO ini' }}</strong>, dengan mendahulukan tanggal pertama box diterima.</p>
                    <p class="plan-help">{{ $stockReviewRequired ? 'Klik Oke untuk mengaktifkan tugas di antrean, kemudian lakukan pemindahan fisik dan konfirmasikan penempatannya.' : 'Daftar berikut menampilkan alokasi FIFO dan kondisi penempatan saat ini.' }} Pemenuhan mengikuti konfirmasi penempatan fisik.</p>
                    <div class="plan-table-scroll" role="region" tabindex="0" aria-label="Daftar alokasi stok FIFO"><table class="table plan-table"><thead><tr><th>Nomor box</th><th>Nomor part / PCS</th><th>Pertama diterima</th><th>Posisi saat ini</th><th>Antarkan ke</th>@unless($stockReviewRequired)<th>Status</th>@endunless</tr></thead><tbody>
                    @foreach($stockAssignments as $assignment)
                        <tr><th scope="row">{{ $assignment->box_number }}</th><td>{{ $assignment->plannedItem->part_number }}<small class="d-block text-muted">{{ number_format($assignment->pcs_quantity, 0, ',', '.') }} PCS</small></td><td>{{ $assignment->first_received_at->format('d/m/Y H:i') }}</td><td>{{ $assignment->stockBox?->currentPallet ? $assignment->stockBox->currentPallet->display_name.' · '.$assignment->stockBox->currentPallet->location_code : 'Belum ditempatkan' }}</td><td><strong>Pallet {{ $assignment->plannedItem->pallet->position }}</strong><span class="d-block">Lokasi {{ $assignment->plannedItem->pallet->location_code }}</span></td>@unless($stockReviewRequired)<td>{{ $assignment->placed_at ? 'Sudah ditempatkan' : ($stockNotice->acknowledged_at ? 'Menunggu penempatan' : 'Belum diaktifkan') }}</td>@endunless</tr>
                    @endforeach
                    </tbody></table></div>
                    @if($stockAssignments->hasPages())<div class="mt-3">{{ $stockAssignments->links('pagination::bootstrap-5') }}</div>@endif
                    <p class="plan-help mt-2 mb-0">Menampilkan {{ $stockAssignments->firstItem() }}–{{ $stockAssignments->lastItem() }} dari {{ $stockAssignments->total() }} box. Antrean tetap dikelompokkan menurut transaksi penerimaan asal.</p>
                @else
                    <p class="mb-1"><strong>Belum ada stok yang dapat dialokasikan saat finalisasi ini.</strong></p><p class="plan-help mb-0">Box yang diterima berikutnya akan dicocokkan ke kebutuhan sesuai FIFO. Tidak ada tugas stok existing yang ditambahkan melalui tinjauan ini.</p>
                @endif
            </div>
            <div class="modal-footer">@if($stockReviewRequired)<form method="POST" action="{{ route('export.pallet-plans.acknowledge-stock', ['deliveryPlan' => $delivery->id, 'notice' => $stockNotice->id]) }}" data-stock-ack-form>@csrf<input type="hidden" name="revision" value="{{ $planning->revision }}"><button type="submit" class="btn btn-primary">{{ $stockAssignments->total() ? 'Oke, masukkan ke antrean' : 'Oke' }}</button></form>@else<button type="button" class="btn btn-primary" data-bs-dismiss="modal">Tutup</button>@endif</div>
        </div></div>
    </div>
    @endif
@endif
