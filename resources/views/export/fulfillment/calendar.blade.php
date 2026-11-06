@php
    $monthLabel = $month->locale('id')->translatedFormat('F Y');
    $previousMonth = $month->subMonth();
    $nextMonth = $month->addMonth();
    $calendarUrl = fn ($date) => route('export.fulfillment.index', array_filter([
        'month' => $date->format('Y-m'),
        'q' => $search,
        'status' => $status,
    ], fn ($value) => $value !== ''));
    $hasFilters = $search !== '' || $status !== '';
@endphp
<section class="plan-panel" aria-labelledby="schedule-month-title">
        <div class="plan-panel-heading export-schedule-heading">
            <h2 id="schedule-month-title">{{ $monthLabel }}</h2>
            <nav class="export-schedule-navigation" aria-label="Navigasi bulan">
                @unless($month->isSameMonth($today))
                    <a class="btn btn-outline-secondary" href="{{ $calendarUrl($today) }}" data-schedule-month="today">Bulan ini</a>
                @endunless
                @if($previousMonth->year >= 1000)
                    <a class="btn btn-outline-secondary" href="{{ $calendarUrl($previousMonth) }}" data-schedule-month="previous" aria-label="Bulan sebelumnya" title="Bulan sebelumnya"><i class="bi bi-chevron-left" aria-hidden="true"></i></a>
                @endif
                @if($nextMonth->year <= 9999)
                    <a class="btn btn-outline-secondary" href="{{ $calendarUrl($nextMonth) }}" data-schedule-month="next" aria-label="Bulan berikutnya" title="Bulan berikutnya"><i class="bi bi-chevron-right" aria-hidden="true"></i></a>
                @endif
            </nav>
        </div>
        <p class="export-schedule-scroll-hint">Geser kalender ke samping untuk melihat semua hari.</p>
        <div class="export-schedule-viewport">
            <div class="export-schedule-scroll" tabindex="0" role="region" aria-label="Kalender delivery ekspor {{ $monthLabel }}">
                <table class="export-schedule-table">
                    <caption class="visually-hidden">Tenggat delivery ekspor untuk {{ $monthLabel }}</caption>
                    <thead>
                        <tr>
                            @foreach(['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'] as $weekday)
                                <th scope="col">{{ $weekday }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($weeks as $week)
                            <tr>
                                @foreach($week as $date)
                                    @if($date)
                                        @php
                                            $dateKey = $date->toDateString();
                                            $deliveries = $plansByDate->get($dateKey, []);
                                        @endphp
                                        <td data-date="{{ $dateKey }}" @class(['is-today' => $date->isSameDay($today)])>
                                            <time datetime="{{ $dateKey }}" @if($date->isSameDay($today)) aria-current="date" @endif>{{ $date->day }}</time>
                                            @if(count($deliveries))
                                                <ul class="export-schedule-deliveries">
                                                    @foreach($deliveries as $plan)
                                                        @php
                                                        $code = $plan->plan_code ?? 'Tanpa kode';
                                                        $description = $code."\nBatas pengiriman ".$date->format('d/m/Y')."\n".($states[$plan->fulfillment_status] ?? '');
                                                        @endphp
                                                        <li><a href="{{ route('export.fulfillment.show', $plan->delivery_id) }}" aria-label="{{ $plan->customer_name }} · {{ $code }}" title="{{ $description }}" data-delivery-preview>{{ $plan->customer_name }}</a></li>
                                                    @endforeach
                                                </ul>
                                            @endif
                                        </td>
                                    @else
                                        <td class="export-schedule-outside"></td>
                                    @endif
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @if($plansByDate->isEmpty())
            <p class="export-schedule-empty">{{ $hasFilters ? 'Tidak ada rencana yang cocok pada bulan ini.' : 'Belum ada permintaan untuk dipenuhi pada bulan ini.' }}</p>
        @endif
</section>
