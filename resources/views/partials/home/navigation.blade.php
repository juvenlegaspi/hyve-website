<header class="site-header site-header--overlay">
    <nav id="site-nav" class="site-nav site-nav--hero">
        <a href="{{ route('home') }}" class="brand-mark">
            <span>
                <strong>HYVE Workspace</strong>
                <small>Mandaue City</small>
            </span>
        </a>

        <button id="menu-toggle" class="menu-toggle" type="button" aria-expanded="false" aria-controls="mobile-menu">
            Menu
        </button>

        <div class="nav-links">
            @foreach ($navigation as $item)
                <a
                    href="{{ $item['href'] }}"
                    class="nav-link"
                    @if ($item['href'] === '#overview')
                        data-nav-mode="home"
                    @elseif ($item['href'] === '#spaces')
                        data-nav-mode="spaces"
                    @else
                        data-nav-anchor
                    @endif
                >{{ $item['label'] }}</a>
            @endforeach
            @guest
                <a href="{{ route('login') }}" class="nav-link nav-link--muted">Log In</a>
            @endguest
            <a href="{{ route('bookings.index') }}" class="button button--dark">Book Now</a>
        </div>
    </nav>

    <div id="mobile-menu" class="mobile-menu hidden">
        @foreach ($navigation as $item)
            <a
                href="{{ $item['href'] }}"
                class="mobile-menu__link"
                @if ($item['href'] === '#overview')
                    data-nav-mode="home"
                @elseif ($item['href'] === '#spaces')
                    data-nav-mode="spaces"
                @else
                    data-nav-anchor
                @endif
            >{{ $item['label'] }}</a>
        @endforeach
        <a href="{{ route('bookings.index') }}" class="button button--dark button--block">Book Now</a>
    </div>
</header>
