<nav id="site-nav" class="sticky top-0 z-[1200] border-b border-[#e9dcc8] bg-[#f6efe6]/96 shadow-[0_12px_30px_rgba(17,23,21,0.08)] backdrop-blur transition duration-300">
        <div class="mx-auto flex max-w-7xl items-center justify-between px-6 py-5 md:px-10">
            <a href="#top" class="flex items-center gap-3">
                <img src="{{ asset('images/logohyve.jpg') }}" alt="HYVE logo" class="h-12 w-12 rounded-full border border-white/60 bg-white/90 object-contain p-1 shadow-lg shadow-black/10">
                <div class="min-w-0">
                    <p class="text-sm font-semibold uppercase tracking-[0.34em] text-[#10231f]">HYVE</p>
                    <p class="hidden text-[10px] uppercase tracking-[0.2em] text-[#74675a] xl:block">Workspaces and Meetings</p>
                </div>
            </a>

            <div class="hidden items-center gap-5 lg:gap-6 xl:gap-8 md:flex">
                @foreach ($navigation as $item)
                    <a href="{{ $item['href'] }}" class="nav-link text-xs font-medium uppercase tracking-[0.16em] text-[#54483e] transition hover:text-[#c49c5b] lg:text-sm lg:tracking-[0.2em]">
                        {{ $item['label'] }}
                    </a>
                @endforeach
            </div>

            <div class="flex items-center gap-3">
                @auth
                    <a href="{{ route('bookings.index') }}" class="hidden rounded-full bg-[#163129] px-5 py-3 text-sm font-semibold uppercase tracking-[0.2em] text-white shadow-lg shadow-[#163129]/20 transition hover:-translate-y-0.5 hover:bg-[#10241f] md:inline-flex">
                        My Booking Page
                    </a>
                @else
                    <a href="{{ route('login') }}" class="hidden rounded-full bg-[#163129] px-5 py-3 text-sm font-semibold uppercase tracking-[0.2em] text-white shadow-lg shadow-[#163129]/20 transition hover:-translate-y-0.5 hover:bg-[#10241f] md:inline-flex">
                        Log In to Book
                    </a>
                @endauth
                <button id="menu-toggle" type="button" class="inline-flex h-11 w-11 items-center justify-center rounded-full border border-[#163129]/15 bg-white/80 text-[#163129] shadow-sm backdrop-blur md:hidden" aria-label="Open menu" aria-expanded="false" aria-controls="mobile-menu">
                    <span class="sr-only">Open menu</span>
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round">
                        <path d="M4 7h16"></path>
                        <path d="M4 12h16"></path>
                        <path d="M4 17h16"></path>
                    </svg>
                </button>
            </div>
        </div>

        <div id="mobile-menu" class="mx-4 hidden rounded-[2rem] border border-white/60 bg-[#f8f2ea]/95 p-4 shadow-2xl shadow-black/10 backdrop-blur md:hidden">
            <div class="flex flex-col gap-2">
                @foreach ($navigation as $item)
                    <a href="{{ $item['href'] }}" class="nav-link rounded-2xl px-4 py-3 text-sm font-semibold uppercase tracking-[0.22em] text-[#163129] transition hover:bg-white">
                        {{ $item['label'] }}
                    </a>
                @endforeach
                @auth
                    <a href="{{ route('bookings.index') }}" class="rounded-2xl bg-[#163129] px-4 py-3 text-sm font-semibold uppercase tracking-[0.22em] text-white transition hover:bg-[#10241f]">
                        My Booking Page
                    </a>
                @else
                    <a href="{{ route('login') }}" class="rounded-2xl bg-[#163129] px-4 py-3 text-sm font-semibold uppercase tracking-[0.22em] text-white transition hover:bg-[#10241f]">
                        Log In
                    </a>
                @endauth
            </div>
        </div>
    </nav>

<header id="top" class="hyve-grid relative">
    <section class="relative mx-auto flex min-h-[calc(100vh-5.75rem)] max-w-7xl items-center px-6 pb-16 pt-16 md:min-h-[calc(100vh-6.25rem)] md:px-10 md:pb-20 md:pt-20">
        <div class="hero-orbit left-[4%] top-[18%] hidden h-24 w-24 lg:block"></div>
        <div class="hero-orbit hero-orbit-delayed right-[10%] top-[28%] hidden h-40 w-40 lg:block"></div>
        <div class="grid items-center gap-14 lg:grid-cols-[1.03fr_0.97fr]">
            <div class="max-w-3xl">
                <p class="eyebrow-chip mb-5 inline-flex rounded-full px-4 py-2 text-xs font-semibold uppercase tracking-[0.32em] text-[#8c692c]">
                    Professional workspace in Mandaue City
                </p>

                <h1 class="font-display max-w-5xl text-5xl leading-[0.98] tracking-[-0.05em] text-[#18130f] md:text-7xl">
                    Where focused work, meaningful meetings, and new professional connections come together.
                </h1>

                <p class="mt-7 max-w-2xl text-lg leading-8 text-[#5d5148] md:text-xl">
                    HYVE is a refined workplace for founders, freelancers, teams, and client-facing professionals who need more than a desk. It is a place to work with clarity, meet with confidence, and connect with people who move your work forward.
                </p>

                <div class="mt-10 flex flex-col gap-4 sm:flex-row">
                    <a href="#contact" class="inline-flex items-center justify-center rounded-full bg-[#c49c5b] px-8 py-4 text-sm font-semibold uppercase tracking-[0.24em] text-[#18130f] shadow-xl shadow-[#c49c5b]/25 transition hover:-translate-y-0.5 hover:bg-[#d1aa6c]">
                        Reserve Your Space
                    </a>
                    <a href="#overview" class="inline-flex items-center justify-center rounded-full border border-[#163129]/18 bg-white/85 px-8 py-4 text-sm font-semibold uppercase tracking-[0.24em] text-[#163129] shadow-sm backdrop-blur transition hover:-translate-y-0.5 hover:border-[#163129] hover:bg-white">
                        Explore More
                    </a>
                </div>

                <div class="mt-10 grid gap-4 sm:grid-cols-3">
                    <div class="metric-card">
                        <p class="micro-label text-[#8c692c]">Flexible Access</p>
                        <p class="mt-3 text-lg font-semibold text-[#163129]">Open 7 Days</p>
                    </div>
                    <div class="metric-card">
                        <p class="micro-label text-[#8c692c]">Meeting Ready</p>
                        <p class="mt-3 text-lg font-semibold text-[#163129]">Private Offices and Rooms</p>
                    </div>
                    <div class="metric-card">
                        <p class="micro-label text-[#8c692c]">Professional Presence</p>
                        <p class="mt-3 text-lg font-semibold text-[#163129]">Guest-Friendly Environment</p>
                    </div>
                </div>
            </div>

            <div class="relative">
                <div class="absolute -left-6 top-10 hidden h-28 w-28 rounded-[2rem] border border-white/50 bg-white/55 backdrop-blur lg:block"></div>
                <div class="absolute -right-8 bottom-14 hidden h-28 w-28 rounded-full bg-[#163129]/10 blur-2xl lg:block"></div>
                <div class="hero-panel relative overflow-hidden rounded-[2.7rem] border border-white/60 p-3 shadow-[0_35px_100px_rgba(22,49,41,0.18)]">
                    <div class="absolute inset-0 bg-[linear-gradient(140deg,rgba(255,255,255,0.18),transparent_42%,rgba(22,49,41,0.16))]"></div>
                    <img src="{{ asset('images/office.png') }}" alt="HYVE office space" class="h-[36rem] w-full rounded-[2.15rem] object-cover object-center">
                </div>

                <div class="dark-panel absolute -bottom-10 left-4 max-w-sm rounded-[2rem] border border-white/12 p-6 text-white shadow-2xl shadow-[#163129]/30 md:left-[-3rem]">
                    <p class="text-xs font-semibold uppercase tracking-[0.3em] text-[#d9b87d]">Book a Tour</p>
                    <p class="mt-3 text-lg leading-7 text-white/85">
                        See how HYVE supports better workdays, better conversations, and a more professional presence from the moment you arrive.
                    </p>
                </div>
            </div>
        </div>
    </section>
</header>
