<section id="booking-flow" class="section-pad section-pad--dark">
    <div class="section-wrap">
        <div class="section-heading-block section-heading-block--dark reveal">
            <p class="eyebrow">Booking Flow</p>
            <h2 class="section-title">Simple steps from browsing to confirmed request.</h2>
        </div>

        <div class="three-grid">
            @foreach ($bookingFlow as $step)
                <article class="flow-card reveal">
                    <span class="tag">{{ $step['step'] }}</span>
                    <h3>{{ $step['title'] }}</h3>
                    <p>{{ $step['description'] }}</p>
                </article>
            @endforeach
        </div>

        <div class="cta-strip reveal">
            <div>
                <strong>Ready to lock in your schedule?</strong>
                <p>Check live availability and submit a request directly from the booking page.</p>
            </div>
            <a href="{{ route('bookings.index') }}" class="button button--light">Go to Booking</a>
        </div>
    </div>
</section>
