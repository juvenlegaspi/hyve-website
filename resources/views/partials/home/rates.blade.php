<section id="rates" class="mx-auto max-w-7xl px-6 pb-20 pt-32 md:px-10 md:pt-36">
    <div class="flex flex-col gap-6 md:flex-row md:items-end md:justify-between">
        <div class="max-w-3xl">
            <p class="section-kicker text-[#8c692c]">Rates and Inclusions</p>
            <h2 class="section-heading mt-5 text-4xl text-[#18130f] md:text-5xl">
                Clear pricing for every way you can work, meet, and host at HYVE.
            </h2>
            <p class="section-copy mt-6 text-lg">
                Review the available rate options in advance so you can choose the right setup for solo work, team sessions, or client-facing meetings before sending your request.
            </p>
        </div>
    </div>

    <div class="mt-12 grid gap-5">
        @foreach ($rates as $rate)
            <article class="feature-card shadow-xl shadow-black/5">
                <button
                    type="button"
                    class="flex w-full items-center justify-between gap-6 p-6 text-left md:p-8"
                    data-rate-toggle
                    aria-expanded="false"
                    aria-controls="rate-panel-{{ $loop->index }}"
                >
                    <div class="min-w-0">
                        <p class="text-xs font-semibold uppercase tracking-[0.24em] text-[#8c692c]">HYVE Rate Card</p>
                        <h3 class="mt-3 text-2xl font-semibold text-[#163129]">{{ $rate['title'] }}</h3>
                        <p class="mt-4 max-w-2xl text-sm leading-7 text-[#5f5449]">
                            Click to review the full pricing structure for this room, including day use, night use, and longer-term options.
                        </p>
                    </div>

                    <div class="flex shrink-0 items-center gap-3">
                        <span class="hidden rounded-full border border-[#163129]/12 px-4 py-2 text-xs font-semibold uppercase tracking-[0.2em] text-[#163129] md:inline-flex">
                            View Rates
                        </span>
                        <span class="flex h-12 w-12 items-center justify-center rounded-full bg-[#163129] text-white transition" data-rate-icon>
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M6 9l6 6 6-6"></path>
                            </svg>
                        </span>
                    </div>
                </button>

                <div id="rate-panel-{{ $loop->index }}" class="hidden border-t border-[#163129]/10 px-6 pb-6 pt-2 md:px-8 md:pb-8" data-rate-panel>
                    <div class="mt-6 grid gap-4 lg:grid-cols-3">
                        <div class="rounded-[1.5rem] bg-[#f7f2eb] p-5">
                            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-[#8c692c]">Day Use</p>
                            <div class="mt-4 space-y-3">
                                @foreach ($rate['day_use'] as $label => $value)
                                    <div class="flex items-start justify-between gap-3 text-sm">
                                        <span class="text-[#5f5449]">{{ $label }}</span>
                                        <span class="font-semibold text-[#163129]">{{ $value }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <div class="rounded-[1.5rem] bg-[#163129] p-5 text-white">
                            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-[#d9b87d]">Night Use</p>
                            <div class="mt-4 space-y-3">
                                @foreach ($rate['night_use'] as $label => $value)
                                    <div class="flex items-start justify-between gap-3 text-sm">
                                        <span class="text-white/72">{{ $label }}</span>
                                        <span class="font-semibold text-white">{{ $value }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <div class="rounded-[1.5rem] bg-[#f7f2eb] p-5">
                            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-[#8c692c]">Monthly and Upgrades</p>
                            <div class="mt-4 space-y-3">
                                @foreach ($rate['memberships'] as $label => $value)
                                    <div class="flex items-start justify-between gap-3 text-sm">
                                        <span class="text-[#5f5449]">{{ $label }}</span>
                                        <span class="font-semibold text-[#163129]">{{ $value }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    <div class="mt-6">
                        <a href="#contact" class="inline-flex items-center rounded-full border border-[#163129]/12 px-5 py-3 text-xs font-semibold uppercase tracking-[0.2em] text-[#163129] transition hover:bg-[#f7f2eb]">
                            Book This Space
                        </a>
                    </div>
                </div>
            </article>
        @endforeach
    </div>

    <div class="mt-12 grid gap-8 lg:grid-cols-[1.05fr_0.95fr]">
        <div class="section-shell rounded-[2rem] p-8">
            <p class="section-kicker text-[#8c692c]">Booking Inclusions</p>
            <div class="mt-6 grid gap-4 sm:grid-cols-2">
                @foreach ($inclusions as $item)
                    <div class="feature-card p-5">
                        <p class="leading-7 text-[#4d4138]">{{ $item }}</p>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="section-shell rounded-[2rem] p-8">
            <p class="section-kicker text-[#8c692c]">Virtual Office</p>
            <div class="mt-6 space-y-4">
                @foreach ($virtualOffice as $package)
                    <div class="{{ $loop->iteration === 2 ? 'feature-card-dark text-white' : 'feature-card' }} p-5">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p class="text-lg font-semibold {{ $loop->iteration === 2 ? 'text-white' : 'text-[#163129]' }}">{{ $package['title'] }}</p>
                                <p class="mt-2 leading-7 {{ $loop->iteration === 2 ? 'text-white/75' : 'text-[#5f5449]' }}">{{ $package['description'] }}</p>
                            </div>
                            <span class="rounded-full {{ $loop->iteration === 2 ? 'bg-white/10 text-[#f3d7a3]' : 'bg-white text-[#163129]' }} px-4 py-2 text-xs font-semibold uppercase tracking-[0.18em]">
                                {{ $package['price'] }}
                            </span>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</section>
