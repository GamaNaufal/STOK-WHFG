<section class="dashboard-stock dashboard-orders" id="{{ $sectionId }}" aria-labelledby="{{ $sectionId }}-title">
    <header class="dashboard-stock-header">
        <div>
            <h2 id="{{ $sectionId }}-title">{{ $title }}</h2>
            <p>{{ $description }}</p>
        </div>
        <span class="dashboard-stock-count">{{ number_format($rows->count()) }} order ditampilkan</span>
    </header>
    @if($rows->isEmpty())
        <div class="dashboard-stock-empty">
            <strong>Tidak ada order untuk ditampilkan.</strong>
            <p>Periksa pilihan filter laporan di atas atau pilih periode lain.</p>
        </div>
    @else
        <p class="dashboard-stock-hint" id="{{ $sectionId }}-help">Geser tabel untuk melihat semua kolom. Kolom Order tetap terlihat.</p>
        <div class="dashboard-stock-scroll" tabindex="0" role="region" aria-labelledby="{{ $sectionId }}-title" aria-describedby="{{ $sectionId }}-help">
            <table class="table dashboard-stock-table dashboard-stock-table--orders mb-0">
                <caption class="visually-hidden">{{ $title }} — order sesuai filter laporan</caption>
                <colgroup><col style="width:11%"><col style="width:24%"><col style="width:15%"><col style="width:14%"><col style="width:14%"><col style="width:10%"><col style="width:12%"></colgroup>
                <thead>
                    <tr>
                        <th scope="col">Order</th>
                        <th scope="col">Customer</th>
                        <th scope="col">Delivery Date</th>
                        <th scope="col" class="dashboard-stock-number">Required</th>
                        <th scope="col" class="dashboard-stock-number">Fulfilled</th>
                        <th scope="col" class="dashboard-stock-number">Rate</th>
                        <th scope="col">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($rows as $row)
                        <tr>
                            <th scope="row">#{{ $row['order_id'] }}</th>
                            <td>{{ $row['customer'] }}</td>
                            <td>{{ $row['delivery_date'] }}</td>
                            <td class="dashboard-stock-number">{{ number_format($row['required']) }}</td>
                            <td class="dashboard-stock-number">{{ number_format($row['fulfilled']) }}</td>
                            <td class="dashboard-stock-number dashboard-order-rate">{{ $row['rate'] }}%</td>
                            <td><span @class(['dashboard-order-status', 'dashboard-order-status--full' => $row['status'] === 'Full', 'dashboard-order-status--partial' => $row['status'] === 'Partial'])>{{ $row['status'] }}</span></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>
