<section class="px-6 pb-24 pt-32 md:px-10 md:pt-36">
    <div class="section-shell mx-auto grid max-w-7xl gap-10 rounded-[2.5rem] p-8 lg:grid-cols-[0.9fr_1.1fr] lg:p-12">
        <div>
            <p class="section-kicker text-[#8c692c]">Booking Flow</p>
            <h2 class="section-heading mt-5 text-4xl text-[#18130f] md:text-5xl">
                A simple path from first visit to confirmed workspace request.
            </h2>
            <p class="section-copy mt-6 max-w-xl text-lg">
                The process is designed to stay clear and professional so you can review the space, connect with the team, and reserve what you need without unnecessary back and forth.
            </p>
        </div>

        <div class="grid gap-4 sm:grid-cols-3">
            @foreach ($bookingFlow as $item)
                <div class="{{ $item['featured'] ? 'feature-card-dark text-white' : 'feature-card' }} p-6">
                    <p class="micro-label {{ $item['featured'] ? 'text-[#d9b87d]' : 'text-[#8c692c]' }}">{{ $item['step'] }}</p>
                    <p class="mt-3 text-xl font-semibold {{ $item['featured'] ? 'text-white' : 'text-[#163129]' }}">{{ $item['title'] }}</p>
                    <p class="mt-2 leading-7 {{ $item['featured'] ? 'text-white/75' : 'text-[#5f5449]' }}">{{ $item['description'] }}</p>
                </div>
            @endforeach
        </div>
    </div>
</section>
