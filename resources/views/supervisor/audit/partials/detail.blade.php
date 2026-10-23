@php($detail = \App\Support\AuditLogPresenter::present($log))
<div class="modal fade audit-detail-modal" id="detailModal{{ $log->id }}" tabindex="-1" aria-labelledby="detailLabel{{ $log->id }}" aria-describedby="detailContext{{ $log->id }}" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable modal-fullscreen-sm-down">
        <div class="modal-content">
            <div class="modal-header">
                <div class="audit-detail-heading">
                    <span class="audit-detail-kicker">Audit #{{ $log->id }}</span>
                    <h5 class="modal-title" id="detailLabel{{ $log->id }}">{{ $detail['action'] }}</h5>
                    <p id="detailContext{{ $log->id }}">{{ $detail['type'] }} · Catatan saat aktivitas terjadi</p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup rincian audit"></button>
            </div>
            <div class="modal-body">
                <dl class="audit-detail-meta">
                    <div><dt>Waktu aktivitas</dt><dd>{{ $log->created_at?->format('d/m/Y H:i:s') ?? 'Tidak tercatat' }}</dd></div>
                    <div><dt>Petugas</dt><dd>{{ $log->user?->name ?? 'Sistem / pengguna tidak tersedia' }}</dd></div>
                </dl>
                <section class="audit-detail-summary" aria-label="Catatan aktivitas">
                    <h6>{{ $log->action === 'box_updated_by_admin_warehouse' ? 'Alasan perubahan' : 'Catatan aktivitas' }}</h6>
                    <div class="audit-description">{{ $detail['description'] }}</div>
                </section>

                @if($detail['changes'])
                    <section class="audit-detail-section" aria-labelledby="changesLabel{{ $log->id }}">
                        <div class="audit-detail-section-heading"><h6 id="changesLabel{{ $log->id }}">Data yang berubah</h6><span>{{ count($detail['changes']) }} perubahan</span></div>
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
                    <section class="audit-detail-section" aria-labelledby="factsLabel{{ $log->id }}">
                        <div class="audit-detail-section-heading"><h6 id="factsLabel{{ $log->id }}">Informasi terkait</h6><span>{{ count($detail['facts']) }} informasi</span></div>
                        <dl class="audit-facts">
                            @foreach($detail['facts'] as $fact)
                                <div><dt>{{ $fact['label'] }}</dt><dd>{{ $fact['value'] }}</dd></div>
                            @endforeach
                        </dl>
                    </section>
                @endif

                @foreach($detail['tables'] as $table)
                    <section class="audit-detail-section" aria-labelledby="auditTableLabel{{ $log->id }}-{{ $loop->index }}">
                        <div class="audit-detail-section-heading"><h6 id="auditTableLabel{{ $log->id }}-{{ $loop->index }}">{{ $table['title'] }}</h6><span>{{ count($table['rows']) }} baris</span></div>
                        <p class="audit-detail-table-hint">Geser tabel untuk melihat semua kolom.</p>
                        <div class="table-responsive audit-detail-table-scroll" tabindex="0" role="region" aria-labelledby="auditTableLabel{{ $log->id }}-{{ $loop->index }}">
                            <table class="table table-sm audit-detail-table mb-0">
                                <caption class="visually-hidden">{{ $table['title'] }}</caption>
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
                    <p class="audit-detail-empty">Aktivitas ini hanya memiliki catatan ringkas. Rincian barang atau perubahan tidak tercatat.</p>
                @endif
                <p class="audit-detail-provenance"><i class="bi bi-clock-history" aria-hidden="true"></i> Informasi mengikuti catatan saat aktivitas terjadi.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>
