<section id="rates" class="section-pad section-pad--alt">
    <div class="section-wrap">
        <div class="section-heading-block section-heading-block--split reveal">
            <div>
                <p class="eyebrow">Rates</p>
                <h2 class="section-title">Transparent pricing you can scan quickly.</h2>
            </div>
            <p class="section-copy section-copy--lead">Everything is arranged so guests and members can compare room options fast, without needing to decode a long pricing sheet.</p>
        </div>

        <div class="rate-list">
            @foreach ($rates as $rate)
                <article class="rate-card rate-card--showcase reveal">
                    <div class="rate-card__header">
                        <span class="rate-card__index">0{{ $loop->iteration }}</span>
                        <h3>{{ $rate['title'] }}</h3>
                    </div>
                    <div class="rate-columns">
                        <div class="rate-panel">
                            <p class="mini-title">Day Use</p>
                            @foreach ($rate['day_use'] as $label => $value)
                                <div class="rate-line"><span>{{ $label }}</span><strong>{{ $value }}</strong></div>
                            @endforeach
                        </div>
                        <div class="rate-panel rate-panel--accent">
                            <p class="mini-title">Night Use</p>
                            @foreach ($rate['night_use'] as $label => $value)
                                <div class="rate-line"><span>{{ $label }}</span><strong>{{ $value }}</strong></div>
                            @endforeach
                        </div>
                        <div class="rate-panel">
                            <p class="mini-title">Memberships</p>
                            @foreach ($rate['memberships'] as $label => $value)
                                <div class="rate-line"><span>{{ $label }}</span><strong>{{ $value }}</strong></div>
                            @endforeach
                        </div>
                    </div>
                    <a href="{{ route('bookings.index') }}" class="rate-card__link">Open booking page</a>
                </article>
            @endforeach
        </div>
    </div>
</section>
