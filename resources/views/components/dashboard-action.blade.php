@props(['icon', 'title', 'description', 'routeName', 'label'])

<li class="dashboard-action">
    <i class="bi bi-{{ $icon }} dashboard-action-icon" aria-hidden="true"></i>
    <div class="dashboard-action-copy">
        <h3>{{ $title }}</h3>
        <p>{{ $description }}</p>
    </div>
    @if(auth()->user()->canAccessRoute($routeName))
        <a href="{{ route($routeName) }}" class="dashboard-action-link">
            {{ $label }} <i class="bi bi-arrow-right" aria-hidden="true"></i>
        </a>
    @else
        <p class="dashboard-action-unavailable">Akses belum tersedia untuk akun ini.</p>
    @endif
</li>
