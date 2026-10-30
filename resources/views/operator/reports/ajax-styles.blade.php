@foreach(['report-stock-input','report-withdrawal','export-delivery-plans'] as $reportStyle)
<link rel="stylesheet" href="{{ asset('css/pages/'.$reportStyle.'.css') }}?v={{ filemtime(public_path('css/pages/'.$reportStyle.'.css')) }}">
@endforeach
