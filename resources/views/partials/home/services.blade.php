<section id="services" class="section-pad">
    <div class="section-wrap">
        <div class="section-heading-block section-heading-block--split reveal">
            <div>
                <p class="eyebrow">How HYVE Helps</p>
                <h2 class="section-title">Built for different work modes in one place.</h2>
            </div>
            <p class="section-copy section-copy--lead">From focused desk time to client meetings and collaborative sessions, the space adapts without feeling temporary and still feels polished enough to host with confidence.</p>
        </div>

        <div class="service-rail">
            @foreach ($serviceHighlights as $index => $service)
                <article class="feature-card feature-card--service reveal">
                    <div class="feature-card__topline">
                        <span class="feature-index">0{{ $index + 1 }}</span>
                        <span class="feature-chip">{{ $index === 0 ? 'Focus' : ($index === 1 ? 'Meet' : 'Connect') }}</span>
                    </div>
                    <h3>{{ $service['title'] }}</h3>
                    <p>{{ $service['description'] }}</p>
                    <div class="feature-card__footer">
                        <span></span>
                    </div>
                </article>
            @endforeach
        </div>
    </div>
</section>
