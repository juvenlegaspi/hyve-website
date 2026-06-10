<section class="relative z-0 px-6 pb-8 md:px-10">
    <div class="mx-auto max-w-7xl">
        <div class="glass-panel hide-scrollbar flex gap-3 overflow-x-auto rounded-[2rem] p-3 relative z-0">
            @foreach ($navigation as $item)
                <a href="{{ $item['href'] }}" class="nav-link whitespace-nowrap rounded-full {{ $loop->first ? 'bg-[#163129] text-white hover:bg-[#10241f]' : 'border border-[#163129]/10 text-[#163129] hover:bg-[#f5eee5]' }} px-5 py-3 text-xs font-semibold uppercase tracking-[0.24em] transition">
                    {{ $item['label'] }}
                </a>
            @endforeach
        </div>
    </div>
</section>

<section id="overview" class="px-6 pb-10 pt-6 md:px-10 md:pt-8">
    <div class="section-shell mx-auto grid max-w-7xl gap-10 rounded-[2.6rem] p-8 lg:grid-cols-[1fr_0.95fr] lg:p-12">
        <div class="relative z-10">
            <p class="section-kicker text-[#8c692c]">Overview</p>
            <h2 class="section-heading mt-5 text-4xl text-[#18130f] md:text-5xl">
                A workplace designed for focused output, professional conversations, and the kind of interactions that lead to new opportunities.
            </h2>
            <p class="section-copy mt-6 max-w-2xl text-lg">
                Located in Mandaue City, HYVE offers more than a place to sit. It gives professionals a polished environment where independent work, private meetings, and natural collaboration can happen in one address.
            </p>

            <div class="mt-8 section-stack max-w-2xl">
                <div class="feature-card p-6">
                    <p class="micro-label text-[#8c692c]">Built for Professional Use</p>
                    <p class="mt-3 text-lg leading-8 text-[#4d4138]">
                        From quiet solo sessions to client-facing meetings, every corner of HYVE is shaped to feel credible, comfortable, and ready for serious work.
                    </p>
                </div>

                <div class="chip-grid">
                    <span>Focused Work</span>
                    <span>Private Meetings</span>
                    <span>Team Sessions</span>
                    <span>Guest-Ready Setup</span>
                </div>
            </div>
        </div>

        <div class="relative z-10 grid gap-4 sm:grid-cols-2">
            @foreach ($quickFacts as $item)
                <div class="reveal {{ $loop->first ? 'feature-card-dark text-white' : 'metric-card' }} p-6">
                    <p class="micro-label {{ $loop->first ? 'text-[#d9b87d]' : 'text-[#8c692c]' }}">{{ $item['label'] }}</p>
                    <p class="mt-4 text-xl leading-8 {{ $loop->first ? 'text-white/84' : 'text-[#22352f]' }}">{{ $item['value'] }}</p>
                </div>
            @endforeach

            <div class="reveal feature-card col-span-full p-6">
                <p class="micro-label text-[#8c692c]">What You Can Expect</p>
                <p class="mt-3 text-base leading-8 text-[#4d4138]">
                    A cleaner workday, a stronger first impression for guests, and a setting that supports both productivity and real professional connection.
                </p>
            </div>
        </div>
    </div>
</section>
