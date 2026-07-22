@php
    $isHomePage = request()->routeIs('home');
@endphp

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
                @php
                    $navigationHref = $isHomePage
                        ? $item['href']
                        : route('home').$item['href'];
                @endphp
                <a
                    href="{{ $navigationHref }}"
                    class="nav-link @if ($isHomePage && $loop->first) is-active @endif"
                    @if ($isHomePage)
                        @if ($item['href'] === '#overview')
                            data-nav-mode="home"
                        @elseif ($item['href'] === '#spaces')
                            data-nav-mode="spaces"
                        @else
                            data-nav-anchor
                        @endif
                    @endif
                >{{ $item['label'] }}</a>
            @endforeach
            @auth
                <a href="{{ route('member.index') }}" class="nav-link @if (request()->routeIs('member.*')) is-active @endif">My bookings</a>
            @endauth
            @guest
                <a href="{{ route('login', ['return_to' => url()->full()]) }}" class="nav-link nav-link--muted">Log In</a>
            @endguest
            <a href="{{ route('bookings.index') }}" class="button button--dark">Book Now</a>
            @auth
                @include('partials.home.member-menu')
            @endauth
        </div>
    </nav>

    <div id="mobile-menu" class="mobile-menu hidden">
        @foreach ($navigation as $item)
            @php
                $navigationHref = $isHomePage
                    ? $item['href']
                    : route('home').$item['href'];
            @endphp
            <a
                href="{{ $navigationHref }}"
                class="mobile-menu__link"
                @if ($isHomePage)
                    @if ($item['href'] === '#overview')
                        data-nav-mode="home"
                    @elseif ($item['href'] === '#spaces')
                        data-nav-mode="spaces"
                    @else
                        data-nav-anchor
                    @endif
                @endif
            >{{ $item['label'] }}</a>
        @endforeach
        @auth
            <a href="{{ route('member.index') }}" class="mobile-menu__link">My bookings</a>
        @endauth
        @guest
            <a href="{{ route('login', ['return_to' => url()->full()]) }}" class="mobile-menu__link">Log In</a>
        @endguest
        @auth
            <form action="{{ route('logout') }}" method="POST">
                @csrf
                <button type="submit" class="button button--ghost button--block">Log Out</button>
            </form>
        @endauth
        <a href="{{ route('bookings.index') }}" class="button button--dark button--block">Book Now</a>
    </div>
</header>
