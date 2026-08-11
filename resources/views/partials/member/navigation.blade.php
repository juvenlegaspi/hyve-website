<header class="site-header site-header--overlay">
    <nav id="site-nav" class="site-nav site-nav--hero" aria-label="Member portal navigation">
        <a href="{{ route('member.dashboard') }}" class="brand-mark">
            <span>
                <strong>HYVE Member</strong>
                <small>Member Portal</small>
            </span>
        </a>

        <button id="menu-toggle" class="menu-toggle" type="button" aria-expanded="false" aria-controls="mobile-menu">
            Menu
        </button>

        <div class="nav-links">
            <a href="{{ route('member.dashboard') }}" class="nav-link @if(request()->routeIs('member.dashboard')) is-active @endif">Dashboard</a>
            <a href="{{ route('member.index') }}" class="nav-link @if(request()->routeIs('member.index') || request()->routeIs('member.bookings.*')) is-active @endif">My bookings</a>
            <a
                href="{{ route('member.dashboard') }}#hyve-announcements"
                class="member-notification-bell"
                aria-label="Member announcements"
                data-member-announcement-notification
                data-feed-url="{{ route('member.announcements.feed') }}"
            >
                <svg viewBox="0 0 20 20" aria-hidden="true"><path d="M4.5 14.2h11l-1.4-2.1V8.5A4.1 4.1 0 0 0 10 4.4a4.1 4.1 0 0 0-4.1 4.1v3.6l-1.4 2.1Z"></path><path d="M8.2 15.2a1.8 1.8 0 0 0 3.6 0M10 2.3v1.5"></path></svg>
                <span class="hidden" data-member-announcement-badge>0</span>
            </a>
            <a href="{{ route('bookings.index') }}" class="button button--dark">Book a space</a>
            @include('partials.home.member-menu', ['memberPortalMode' => true])
        </div>
    </nav>

    <div id="mobile-menu" class="mobile-menu hidden">
        <a href="{{ route('member.dashboard') }}" class="mobile-menu__link">Dashboard</a>
        <a href="{{ route('member.index') }}" class="mobile-menu__link">My bookings</a>
        <a
            href="{{ route('member.dashboard') }}#hyve-announcements"
            class="mobile-menu__link member-mobile-announcement-link"
            data-member-announcement-notification
            data-feed-url="{{ route('member.announcements.feed') }}"
        >Announcements <span class="hidden" data-member-announcement-badge>0</span></a>
        <a href="{{ route('member.profile.edit') }}" class="mobile-menu__link">Edit profile</a>
        <a href="{{ route('member.password.edit') }}" class="mobile-menu__link">Change password</a>
        <a href="{{ route('bookings.index') }}" class="button button--dark button--block">Book a space</a>
        <form action="{{ route('logout') }}" method="POST">
            @csrf
            <button type="submit" class="button button--ghost button--block">Log out</button>
        </form>
    </div>
</header>
