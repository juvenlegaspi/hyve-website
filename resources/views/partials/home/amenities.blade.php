<section id="amenities" class="mx-auto max-w-7xl px-6 pb-24 pt-32 md:px-10 md:pt-36">
    <div class="grid gap-8 lg:grid-cols-[0.88fr_1.12fr]">
        <div class="section-shell rounded-[2.5rem] p-8 md:p-10">
            <p class="section-kicker text-[#8c692c]">Amenities</p>
            <h2 class="section-heading mt-5 text-4xl text-[#18130f]">
                The practical details that make every session feel smoother, more comfortable, and ready for real work.
            </h2>
            <p class="section-copy mt-6 text-lg">
                Great workspaces are not only about furniture. They are about flow, convenience, and creating an environment where people can focus, collaborate, and show up well.
            </p>

            <div class="mt-8 feature-card p-6">
                <p class="micro-label text-[#8c692c]">Operational Advantage</p>
                <p class="mt-3 text-base leading-8 text-[#4d4138]">
                    HYVE is designed to reduce friction during the workday, so guests, teams, and solo professionals can settle in quickly and stay focused longer.
                </p>
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($amenities as $amenity)
                <div class="reveal metric-card p-5">
                    <p class="text-sm font-semibold uppercase tracking-[0.2em] text-[#163129]">{{ $amenity }}</p>
                </div>
            @endforeach
        </div>
    </div>
</section>
