<section id="why-hyve" class="section-pad">
    <div class="section-wrap why-grid">
        <div class="reveal">
            <p class="eyebrow">Why People Choose HYVE</p>
            <h2 class="section-title">More than a room rental. It feels ready the moment you arrive.</h2>
        </div>

        <div class="reason-list">
            @foreach ($reasons as $reason)
                <article class="reason-card reveal">
                    <strong>Reason {{ $loop->iteration }}</strong>
                    <p>{{ $reason }}</p>
                </article>
            @endforeach
        </div>
    </div>
</section>
