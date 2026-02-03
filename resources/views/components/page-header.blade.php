@props([
    'title' => '',
    'subtitle' => '',
    'icon' => 'house',
    'action' => null
])

<div class="row mb-4">
    <div class="col-12">
        <div style="background: linear-gradient(135deg, #0C7779 0%, #249E94 100%); 
                    color: white; 
                    padding: 40px 30px; 
                    border-radius: 12px; 
                    box-shadow: 0 8px 24px rgba(12, 119, 121, 0.15);">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="h2" style="margin: 0 0 10px 0; font-weight: 700;">
                        <i class="bi bi-{{ $icon }}"></i> {{ $title }}
                    </h1>
                    <p style="margin: 0; opacity: 0.95; font-size: 15px;">
                        {{ $subtitle }}
                    </p>
                </div>
                @if($action)
                    <div>
                        {{ $action }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
