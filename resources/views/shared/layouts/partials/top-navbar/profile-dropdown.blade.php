<div class="dropdown">
    <button class="avatar-trigger" aria-label="Menu akun" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="{{ auth()->user()->name }} · {{ $navigationContext['role_label'] }}">
        <span class="avatar-circle" aria-hidden="true">{{ $avatarInitials }}</span>
        <span class="navigation-account-name">{{ auth()->user()->name }}</span>
        <i class="bi bi-chevron-down" aria-hidden="true"></i>
    </button>
    <ul class="dropdown-menu dropdown-menu-end navigation-account-menu">
        <li class="navigation-account-summary"><strong>{{ auth()->user()->name }}</strong><span>{{ $navigationContext['role_label'] }}</span></li>
        <li><hr class="dropdown-divider"></li>
        <li>
            <a class="dropdown-item" href="{{ route('profile.edit') }}">
                <i class="bi bi-person me-2" aria-hidden="true"></i> Profil Saya
            </a>
        </li>
        <li><hr class="dropdown-divider"></li>
        <li>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="dropdown-item text-danger">
                    <i class="bi bi-box-arrow-right me-2" aria-hidden="true"></i> Keluar
                </button>
            </form>
        </li>
    </ul>
</div>
