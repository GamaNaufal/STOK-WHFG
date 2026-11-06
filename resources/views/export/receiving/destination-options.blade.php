<optgroup label="Lokasi kosong">
    @foreach($locations as $loc)
        <option value="location:{{ $loc->id }}" data-box-count="0" data-guide-pallet="Pallet cadangan" data-guide-location="{{ $loc->code }}">{{ $loc->code }} · pallet cadangan baru</option>
    @endforeach
</optgroup>
<optgroup label="Pallet ekspor existing">
    @foreach($pallets as $physical)
        @continue($physical->id === ($excludePalletId ?? null))
        @php($limit = $physical->spareBoxLimit())
        <option value="pallet:{{ $physical->id }}" data-location-id="{{ $physical->master_location_id }}" data-box-count="{{ $physical->boxes_count }}" data-box-limit="{{ $limit }}" data-guide-pallet="{{ $physical->display_name }}" data-guide-location="{{ $physical->location_code }}" @disabled($limit !== null && $physical->boxes_count >= $limit)>{{ $physical->display_name }} · {{ $physical->location_code }} · {{ $physical->capacityLabel($physical->boxes_count) }}</option>
    @endforeach
</optgroup>
