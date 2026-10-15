@foreach(collect($featureNavigation ?? [])->groupBy('group') as $group => $items)
    @php
        $groupId = 'featureMenu'.\Illuminate\Support\Str::slug($group);
        $active = $items->contains(fn($item) => in_array($item['key'], app(\App\Services\FeatureAccessService::class)->routePermissions(request()->route()?->getName()), true));
    @endphp
    <li class="nav-item">
        <a class="nav-link menu-group-toggle {{ $active ? '' : 'collapsed' }}" data-bs-toggle="collapse"
           href="#{{ $groupId }}" role="button" aria-expanded="{{ $active ? 'true' : 'false' }}" aria-controls="{{ $groupId }}">
            <span class="menu-group-label">{{ $group }}</span><i class="bi bi-chevron-down menu-chevron"></i>
        </a>
        <div class="collapse {{ $active ? 'show' : '' }}" id="{{ $groupId }}">
            <ul class="nav flex-column ms-3 mt-2 mb-1 submenu">
                @foreach($items as $item)
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs($item['route']) ? 'active' : '' }}" href="{{ route($item['route']) }}">
                            <i class="bi bi-{{ $item['icon'] }}"></i> {{ $item['label'] }}
                            @php
                                $badgeCount = match ($item['key']) {
                                    'delivery_schedule' => $pendingDeliveryCount ?? 0,
                                    'delivery_approval' => $pendingApprovalCount ?? 0,
                                    'delivery_issues' => $pendingScanIssueCount ?? 0,
                                    default => 0,
                                };
                            @endphp
                            @if($badgeCount > 0)<span class="badge bg-danger rounded-pill ms-auto">{{ $badgeCount }}</span>@endif
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
    </li>
@endforeach
