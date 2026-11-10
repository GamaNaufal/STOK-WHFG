@if($customer === '' || $deliveries->isEmpty())
    <p class="stock-readiness-message">{{ $customer === '' ? 'Pilih customer untuk menampilkan rencana delivery yang masih aktif.' : 'Tidak ada rencana delivery aktif untuk customer ini.' }}</p>
@else
    <div class="stock-readiness-grid">
        <section aria-labelledby="stock-readiness-deliveries">
            <h3 id="stock-readiness-deliveries">Progres delivery <span>{{ $deliveries->count() }}</span></h3>
            <div class="stock-delivery-list">
                @foreach($deliveries as $row)
                    @php($delivery = $row['delivery'])
                    <article class="stock-delivery-progress">
                        <header>
                            <div>
                                <strong>{{ $delivery->plan_code }}</strong>
                                <small>Tenggat {{ $delivery->delivery_deadline->format('d/m/Y') }}</small>
                            </div>
                            @if(($delivery->palletPlan?->status === 'final' || $delivery->palletPlan?->entered_fulfillment) && auth()->user()->canAccessRoute('export.fulfillment.show'))
                                <a href="{{ route('export.fulfillment.show', $delivery) }}">Lihat delivery</a>
                            @elseif(auth()->user()->canAccessRoute('export.delivery-plans.show'))
                                <a href="{{ route('export.delivery-plans.show', $delivery) }}">Lihat rencana</a>
                            @endif
                        </header>
                        <dl aria-label="Jumlah box per tahap">
                            <div><dt>Butuh</dt><dd>{{ $row['required'] }}</dd></div>
                            <div><dt>Siap kirim</dt><dd>{{ $row['ready'] }}</dd></div>
                            <div><dt>Di pallet, belum siap</dt><dd>{{ $row['placed'] }}</dd></div>
                            <div><dt>Reservasi, belum ditempatkan</dt><dd>{{ $row['reserved'] }}</dd></div>
                            <div><dt>Belum dialokasikan</dt><dd>{{ $row['unallocated'] }}</dd></div>
                        </dl>
                    </article>
                @endforeach
            </div>
        </section>
        <section aria-labelledby="stock-readiness-shortages">
            <h3 id="stock-readiness-shortages">Kebutuhan vs stok bersih</h3>
            @if($shortages->isEmpty())
                <p class="stock-readiness-message">Semua kebutuhan sudah memiliki alokasi.</p>
            @else
                <p class="stock-readiness-note">Gabungan kebutuhan yang belum dialokasikan untuk customer ini. Stok bersih memperhitungkan reservasi seluruh customer.</p>
                <div class="plan-table-scroll stock-readiness-table" role="region" tabindex="0" aria-label="Kebutuhan dan stok bersih per part">
                    <table class="table plan-table">
                        <thead><tr><th scope="col">Nomor part</th><th scope="col">Belum dialokasi</th><th scope="col">Stok bersih cocok</th><th scope="col">Masih kurang</th></tr></thead>
                        <tbody>
                            @foreach($shortages as $row)
                                <tr>
                                    <th scope="row">{{ $row['part_number'] }}<small>{{ $row['pcs_per_box'] }} PCS/box{{ $row['is_not_full'] ? ' · Not Full' : '' }}</small></th>
                                    <td>{{ $row['unallocated'] }}</td>
                                    <td>
                                        @if($row['available'] > 0)
                                            <a href="{{ route('export.stock.index', ['customer' => $customer, 'status' => 'free', 'part_number' => $row['part_number'], 'pcs_per_box' => $row['pcs_per_box']]) }}#export-stock-results" aria-label="Lihat {{ $row['available'] }} box stok bersih untuk part {{ $row['part_number'] }}, {{ $row['pcs_per_box'] }} PCS per box">{{ $row['available'] }}</a>
                                        @else
                                            0
                                        @endif
                                    </td>
                                    <td><strong @class(['stock-shortage' => $row['remaining'] > 0])>{{ $row['remaining'] }}</strong></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <p class="stock-readiness-note">Semua angka dalam box. Stok yang cocok belum direservasi.</p>
            @endif
        </section>
    </div>
@endif
