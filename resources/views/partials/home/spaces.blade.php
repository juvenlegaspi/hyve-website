<section id="spaces" class="relative overflow-hidden bg-[#163129] px-6 pb-24 pt-32 text-white md:px-10 md:pt-36">
    <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_top_left,_rgba(196,156,91,0.18),_transparent_24rem),linear-gradient(180deg,rgba(255,255,255,0.02),transparent_30%)]"></div>
    <div class="mx-auto max-w-7xl">
        <div class="flex flex-col gap-6 md:flex-row md:items-end md:justify-between">
            <div class="max-w-2xl">
                <p class="section-kicker text-[#d9b87d]">Workspace Options</p>
                <h2 class="section-heading mt-5 text-4xl md:text-5xl">
                    Choose the setting that matches how you work, how you meet, and how you want people to experience your brand.
                </h2>
            </div>
            <a href="#contact" class="action-pill border border-white/20 text-white transition hover:border-[#d9b87d] hover:text-[#d9b87d]">
                Request a Quote
            </a>
        </div>

        <div class="mt-14 grid gap-8 lg:grid-cols-2 2xl:grid-cols-4">
            @foreach ($spaces as $space)
                <article class="reveal group overflow-hidden rounded-[2rem] border border-white/10 bg-[linear-gradient(180deg,rgba(255,255,255,0.08),rgba(255,255,255,0.04))] shadow-xl shadow-black/10 backdrop-blur transition hover:-translate-y-1.5">
                    <div class="relative overflow-hidden">
                        <img src="{{ asset($space['image']) }}" alt="{{ $space['title'] }}" class="h-72 w-full object-cover transition duration-500 group-hover:scale-[1.03]">
                        <div class="absolute inset-x-0 bottom-0 h-28 bg-gradient-to-t from-[#163129] via-[#163129]/40 to-transparent"></div>
                        <span class="absolute left-6 top-6 rounded-full bg-white/10 px-4 py-2 text-xs font-semibold uppercase tracking-[0.2em] text-[#f3d7a3] backdrop-blur">{{ $space['tag'] }}</span>
                    </div>
                    <div class="space-y-5 p-8">
                        <h3 class="text-2xl font-semibold">{{ $space['title'] }}</h3>
                        <p class="leading-8 text-white/75">{{ $space['description'] }}</p>
                        <div class="flex flex-wrap gap-2">
                            @foreach ($space['features'] as $feature)
                                <span class="rounded-full border border-white/12 px-3 py-2 text-xs font-medium uppercase tracking-[0.18em] text-white/75">{{ $feature }}</span>
                            @endforeach
                        </div>
                        <p class="text-sm uppercase tracking-[0.2em] text-[#d9b87d]">{{ $space['note'] }}</p>
                        <a href="#contact" class="inline-flex items-center text-sm font-semibold uppercase tracking-[0.24em] text-[#d9b87d] transition hover:text-[#edd7aa]">
                            Inquire About This Space
                        </a>
                    </div>
                </article>
            @endforeach
        </div>
    </div>
</section>
