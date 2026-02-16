<div class="top-navbar sticky-top">
    <div class="navbar-inner px-3">
        @include('shared.layouts.partials.top-navbar.sidebar-toggle')

        @include('shared.layouts.partials.top-navbar.global-search-form')

        <div class="topbar-future-slot" title="Space reserved for future features"></div>

        @if(auth()->check())
            @include('shared.layouts.partials.top-navbar.profile-dropdown')
        @endif
    </div>
</div>