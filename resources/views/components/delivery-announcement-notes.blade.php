@props(['delivery', 'surface', 'boxes' => null, 'parts' => null, 'context' => null, 'targets' => null, 'print' => false, 'instructions' => null])

@php($instructions ??= app(\App\Services\DeliveryAnnouncementDisplay::class)->notes($delivery, $surface, auth()->user(), $boxes, $parts, $context, $targets))
@if($instructions->isNotEmpty())
    <div {{ $attributes->class($print ? [] : ['alert alert-warning mb-3']) }}
        @if($print) style="border:1px solid #555;padding:6px 8px;margin:6px 0;font-size:0.9em" @endif>
        <strong>Keterangan khusus</strong>
        @foreach($instructions as $instruction)
            <div style="margin-top:0.4em;overflow-wrap:anywhere">
                <strong>Part {{ $instruction['part'] }}</strong>
                @if($instruction['pending'])
                    <span>· Menunggu penetapan ID Box</span>
                @else
                    <span>· {{ $instruction['all_boxes'] ? 'Semua box part ini' : count($instruction['box_numbers']).' box terpilih' }}</span>
                @endif
                <div style="white-space:pre-line">{{ $instruction['message'] }}</div>
                @if($instruction['box_numbers'])
                    @if(!$print && count($instruction['box_numbers']) > 8)
                        <details><summary>Lihat {{ count($instruction['box_numbers']) }} ID Box</summary>
                            <div>ID Box {{ implode(', ', $instruction['box_numbers']) }}</div>
                        </details>
                    @else
                        <div>ID Box {{ implode(', ', $instruction['box_numbers']) }}</div>
                    @endif
                @endif
            </div>
        @endforeach
    </div>
@endif
