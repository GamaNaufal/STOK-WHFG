@foreach(collect($featureNavigation ?? [])->groupBy('group') as $group => $items)
    @php
        $groupId = 'featureMenu'.\Illuminate\Support\Str::slug($group);
        $active = $items->contains('active', true);
    @endphp
    <li class="nav-item">
        <button type="button" class="nav-link menu-group-toggle {{ $active ? '' : 'collapsed' }}" data-bs-toggle="collapse"
           data-bs-target="#{{ $groupId }}" aria-expanded="{{ $active ? 'true' : 'false' }}" aria-controls="{{ $groupId }}">
            <span class="menu-group-label">{{ $items->first()['group_label'] }}</span><i class="bi bi-chevron-down menu-chevron" aria-hidden="true"></i>
        </button>
        <div class="collapse {{ $active ? 'show' : '' }}" id="{{ $groupId }}">
            <ul class="nav flex-column ms-3 mt-2 mb-1 submenu">
                @foreach($items as $item)
                    @php
                        $itemActive = $item['active'];
                    @endphp
                    <li class="nav-item">
                        <a class="nav-link {{ $itemActive ? 'active' : '' }}" href="{{ route($item['route']) }}" title="{{ $item['label'] }}" aria-label="{{ $item['label'] }}" @if($itemActive)aria-current="page"@endif>
                            <i class="bi bi-{{ $item['icon'] }}" aria-hidden="true"></i><span class="navigation-item-label">{{ $item['short_label'] }}</span>
                            @php
                                $badgeCount = match ($item['key']) {
                                    'delivery_schedule' => $pendingDeliveryCount ?? 0,
                                    'delivery_approval' => $pendingApprovalCount ?? 0,
                                    'delivery_issues' => $pendingScanIssueCount ?? 0,
                                    'not_full_request', 'not_full_approval' => $exportNotFullPending ?? 0,
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
