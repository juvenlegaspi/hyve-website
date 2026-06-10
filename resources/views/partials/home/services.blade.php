<section id="services" class="mx-auto max-w-7xl px-6 pb-20 pt-32 md:px-10 md:pt-36">
    <div class="flex flex-col gap-6 md:flex-row md:items-end md:justify-between">
        <div class="max-w-2xl">
            <p class="section-kicker text-[#8c692c]">Services</p>
            <h2 class="section-heading mt-5 text-4xl text-[#18130f] md:text-5xl">
                Workspace options built for modern professionals who need flexibility without compromising on presence.
            </h2>
        </div>
        <a href="#contact" class="action-pill border border-[#163129]/12 text-[#163129] transition hover:bg-white">
            Ask for Availability
        </a>
    </div>

    <div class="mt-12 grid gap-6 lg:grid-cols-3">
        @foreach ($serviceHighlights as $service)
            <article class="feature-card p-8 {{ $loop->iteration === 2 ? 'translate-y-0 lg:translate-y-6' : '' }}">
                <p class="micro-label text-[#8c692c]">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</p>
                <h3 class="mt-4 text-2xl font-semibold text-[#163129]">{{ $service['title'] }}</h3>
                <p class="mt-4 section-copy">{{ $service['description'] }}</p>
                <div class="mt-6 flex items-center gap-3 text-sm font-semibold uppercase tracking-[0.2em] text-[#163129]">
                    <span class="h-2.5 w-2.5 rounded-full bg-[#c49c5b]"></span>
                    Designed for professional use
                </div>
            </article>
        @endforeach
    </div>
</section>
