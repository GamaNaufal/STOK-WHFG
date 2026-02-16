<div class="dropdown">
    <button class="avatar-trigger" type="button" data-bs-toggle="dropdown" aria-expanded="false">
        <span class="avatar-circle">{{ $avatarInitials }}</span>
        <span class="d-none d-md-inline" style="font-size: 0.9rem; color:#374151;">{{ auth()->user()->name }}</span>
        <i class="bi bi-chevron-down" style="font-size: 0.8rem; color:#6b7280;"></i>
    </button>
    <ul class="dropdown-menu dropdown-menu-end shadow-sm" style="border:1px solid #e5e9f0; border-radius:10px;">
        <li>
            <a class="dropdown-item" href="{{ route('profile.edit') }}">
                <i class="bi bi-person me-2"></i> Kelola Profil
            </a>
        </li>
        <li><hr class="dropdown-divider"></li>
        <li>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="dropdown-item text-danger">
                    <i class="bi bi-box-arrow-right me-2"></i> Logout
                </button>
            </form>
        </li>
    </ul>
</div>