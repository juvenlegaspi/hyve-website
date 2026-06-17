<section id="spaces" class="section-pad">
    <div class="section-wrap">
        <div class="section-heading-block section-heading-block--split reveal">
            <div>
                <p class="eyebrow">Workspace Options</p>
                <h2 class="section-title">Choose the setup that matches your day.</h2>
            </div>
            <p class="section-copy section-copy--lead">Every room is built for a different kind of work rhythm, from solo desk sessions to guest-facing meetings and small team planning.</p>
        </div>

        <div class="space-list">
            @foreach ($spaces as $space)
                <article class="space-card space-card--showcase reveal">
                    <div class="space-card__media">
                        <img src="{{ asset($space['image']) }}" alt="{{ $space['title'] }}">
                    </div>
                    <div class="space-card__content">
                        <div class="space-card__header">
                            <span class="tag">{{ $space['tag'] }}</span>
                            <span class="space-card__count">0{{ $loop->iteration }}</span>
                        </div>
                        <h3>{{ $space['title'] }}</h3>
                        <p>{{ $space['description'] }}</p>
                        <div class="chip-row chip-row--soft">
                            @foreach ($space['features'] as $feature)
                                <span>{{ $feature }}</span>
                            @endforeach
                        </div>
                        <small class="space-card__note">{{ $space['note'] }}</small>
                        <a href="{{ route('bookings.index') }}" class="space-card__link">Book this setup</a>
                    </div>
                </article>
            @endforeach
        </div>
    </div>
</section>
