<section id="amenities" class="section-pad">
    <div class="section-wrap">
        <div class="section-heading-block section-heading-block--split reveal">
            <div>
                <p class="eyebrow">Amenities</p>
                <h2 class="section-title">Small details that make workdays easier.</h2>
            </div>
            <p class="section-copy section-copy--lead">The day feels smoother when the basics are already handled well, from comfort and connectivity to the overall guest-ready atmosphere.</p>
        </div>

        <div class="amenity-grid">
            @foreach ($amenities as $amenity)
                <article class="amenity-card amenity-card--stacked reveal">
                    <span class="amenity-dot"></span>
                    <p>{{ $amenity }}</p>
                    <small>Included in the HYVE workspace experience</small>
                </article>
            @endforeach
        </div>
    </div>
</section>
