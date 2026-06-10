<section id="why-hyve" class="mx-auto max-w-7xl px-6 pb-24 pt-32 md:px-10 md:pt-36">
    <div class="grid gap-8 lg:grid-cols-[0.88fr_1.12fr]">
        <div class="section-shell-dark rounded-[2.5rem] p-8 text-white md:p-10">
            <p class="section-kicker text-[#d9b87d]">Why HYVE</p>
            <h2 class="section-heading mt-5 text-4xl">
                A better place to do business, build momentum, and meet people who matter to your work.
            </h2>
            <p class="mt-6 text-lg leading-8 text-white/72">
                HYVE brings together professional setting, flexible access, and a welcoming atmosphere so work stays productive while conversations stay open.
            </p>
        </div>

        <div class="grid gap-5 sm:grid-cols-2">
            @foreach ($reasons as $item)
                <div class="reveal feature-card p-7">
                    <p class="micro-label text-[#8c692c]">Reason {{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</p>
                    <p class="mt-4 text-xl font-semibold leading-9 text-[#163129]">{{ $item }}</p>
                </div>
            @endforeach
        </div>
    </div>
</section>
