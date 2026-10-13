@php($detail = \App\Support\AuditLogPresenter::present($log))
<div class="modal fade audit-detail-modal" id="detailModal{{ $log->id }}" tabindex="-1" aria-labelledby="detailLabel{{ $log->id }}" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable modal-fullscreen-sm-down">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <div class="small text-muted mb-1">{{ $detail['type'] }} · Audit #{{ $log->id }}</div>
                    <h5 class="modal-title" id="detailLabel{{ $log->id }}">{{ $detail['action'] }}</h5>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup rincian audit"></button>
            </div>
            <div class="modal-body">
                <dl class="row g-3 mb-4">
                    <div class="col-sm-6"><dt>Waktu aktivitas</dt><dd>{{ $log->created_at?->format('d/m/Y H:i:s') ?? 'Tidak tercatat' }}</dd></div>
                    <div class="col-sm-6"><dt>Operator</dt><dd>{{ $log->user?->name ?? 'Sistem / pengguna tidak tersedia' }}</dd></div>
                </dl>
                <section class="audit-detail-summary mb-4" aria-label="Catatan aktivitas">
                    <h6>{{ $log->action === 'box_updated_by_admin_warehouse' ? 'Alasan perubahan' : 'Catatan aktivitas' }}</h6>
                    <div class="audit-description">{{ $detail['description'] }}</div>
                </section>

                @if($detail['changes'])
                    <section class="mb-4" aria-labelledby="changesLabel{{ $log->id }}">
                        <h6 id="changesLabel{{ $log->id }}">Data yang berubah <span class="badge bg-light text-dark">{{ count($detail['changes']) }}</span></h6>
                        <div class="audit-change-heading" aria-hidden="true"><span>Informasi</span><span>Sebelum</span><span>Sesudah</span></div>
                        @foreach($detail['changes'] as $change)
                            <div class="audit-change-row">
                                <strong>{{ $change['label'] }}</strong>
                                <div><span class="audit-value-label">Sebelum: </span>{{ $change['before'] }}</div>
                                <div class="audit-value-after"><span class="audit-value-label">Sesudah: </span>{{ $change['after'] }}</div>
                            </div>
                        @endforeach
                    </section>
                @endif

                @if($detail['facts'])
                    <section class="mb-4" aria-labelledby="factsLabel{{ $log->id }}">
                        <h6 id="factsLabel{{ $log->id }}">Informasi terkait</h6>
                        <dl class="audit-facts mb-0">
                            @foreach($detail['facts'] as $fact)
                                <div><dt>{{ $fact['label'] }}</dt><dd>{{ $fact['value'] }}</dd></div>
                            @endforeach
                        </dl>
                    </section>
                @endif

                @foreach($detail['tables'] as $table)
                    <section class="mb-4">
                        <h6>{{ $table['title'] }} <span class="badge bg-light text-dark">{{ count($table['rows']) }}</span></h6>
                        <div class="table-responsive" tabindex="0" role="region" aria-label="{{ $table['title'] }}">
                            <table class="table table-sm audit-detail-table mb-0">
                                <thead><tr>@foreach($table['columns'] as $column)<th scope="col">{{ $column }}</th>@endforeach</tr></thead>
                                <tbody>
                                    @foreach($table['rows'] as $row)
                                        <tr>@foreach($row as $value)<td>{{ $value }}</td>@endforeach</tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </section>
                @endforeach

                @if(!$detail['changes'] && !$detail['facts'] && !$detail['tables'])
                    <p class="text-muted mb-3">Aktivitas ini hanya memiliki catatan ringkas. Rincian barang atau perubahan tidak tercatat.</p>
                @endif
                <p class="small text-muted mb-0"><i class="bi bi-clock-history" aria-hidden="true"></i> Informasi mengikuti catatan saat aktivitas terjadi.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>
