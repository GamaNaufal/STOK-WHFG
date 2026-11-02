<div class="top-navbar sticky-top">
    <div class="navbar-inner px-3">
        @include('shared.layouts.partials.top-navbar.sidebar-toggle')

        <nav class="navigation-context" aria-label="Jejak navigasi"><ol>
            @foreach($navigationContext['breadcrumbs'] ?? [] as $crumb)
                <li @if($loop->last)aria-current="page"@endif>
                    @if($crumb['url'])<a href="{{ $crumb['url'] }}">{{ $crumb['label'] }}</a>@else<span>{{ $crumb['label'] }}</span>@endif
                </li>
            @endforeach
        </ol></nav>

        @include('shared.layouts.partials.top-navbar.global-search-form')



        @if(auth()->check())
            @include('shared.layouts.partials.top-navbar.profile-dropdown')
        @endif
    </div>
</div>
