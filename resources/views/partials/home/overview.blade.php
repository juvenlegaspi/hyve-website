<section id="overview" class="hero-section hero-section--video">
    <video class="hero-video" autoplay muted loop playsinline>
        <source src="{{ asset('videos/hyve-hero.mp4') }}" type="video/mp4">
    </video>
    <div class="hero-overlay"></div>

    <div class="hero-content reveal">
        <p class="hero-location">Mandaue City, Cebu</p>
        <h1 class="hero-title hero-title--video">HYVE WORKSPACE</h1>
        <p class="hero-copy hero-copy--video">
            Professional rooms, focused desks, and meeting-ready spaces for modern teams and independent professionals.
        </p>

        <div class="hero-actions hero-actions--center">
            <a href="{{ route('bookings.index') }}" class="button button--light">Reserve a Slot</a>
            <a href="#spaces" class="button button--outline-light">Browse Spaces</a>
        </div>
    </div>

    

    <div class="hero-facts">
        @foreach ($quickFacts as $fact)
            <article class="hero-fact">
                <span>{{ $fact['label'] }}</span>
                <strong>{{ $fact['value'] }}</strong>
            </article>
        @endforeach
    </div>
</section>
