@php
    $isPdfRender = $isPdf ?? false;
    $logoSrc = $isPdfRender
        ? (file_exists(public_path('logo.png')) ? public_path('logo.png') : public_path('favicon.ico'))
        : asset('logo.png');
@endphp

<div class="company-letterhead">
    <div class="letterhead-logo">
        <img src="{{ $logoSrc }}" alt="Company Logo">
    </div>
    <div class="letterhead-info">
        <div class="company-name">PT. YAMATOGOMU INDONESIA</div>
        <div>Kawasan Industri Indotaisei</div>
        <div>Blok K - 6, Cikampek</div>
        <div>Jawa Barat - Indonesia 41373</div>
        <div>Phone : 0264 - 351216, 351217&nbsp;&nbsp;Fax : 0264 - 351137</div>
    </div>
</div>