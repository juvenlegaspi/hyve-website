<section id="overview" class="hero-section hero-section--video">
    <video
        class="hero-video"
        muted
        loop
        playsinline
        preload="none"
        fetchpriority="high"
        poster="{{ asset('images/optimized/common-area-1.webp') }}"
        data-hero-video
        data-src="{{ asset('videos/hyve-hero.mp4') }}?v=20260720"
        aria-hidden="true"
    ></video>
    <div class="hero-overlay"></div>

    <div class="hero-content hero-content--workspace reveal">
        <p class="hero-location">Mandaue City, Cebu</p>
        <h1 class="hero-title hero-title--video hero-title--workspace">
            <span class="hero-title__line">HYVE</span>
            <span class="hero-title__line">WORKSPACE</span>
        </h1>
        <p class="hero-copy hero-copy--video">
            Professional rooms, focused desks, and meeting-ready spaces for modern teams and independent professionals.
        </p>

        <div class="hero-actions hero-actions--center">
            <a href="{{ route('bookings.index') }}" class="button button--light">Reserve a Slot</a>
            <a href="#spaces" class="button button--outline-light">Browse Spaces</a>
        </div>
    </div>
</section>
