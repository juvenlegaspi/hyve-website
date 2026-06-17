@php
    $spaceCatalog = collect($spaces)->map(function (array $space) use ($rates): array {
        preg_match('/\((\d+)\s*Seats?\)/i', $space['title'], $matches);
        $capacity = isset($matches[1]) ? (int) $matches[1] : ($space['title'] === 'Common Area' ? 1 : 8);
        $matchingRate = collect($rates)->firstWhere('title', $space['title']);
        $startingRate = $matchingRate['day_use']['2 hrs min'] ?? $matchingRate['day_use']['Daily'] ?? 'Ask HYVE';

        return [
            ...$space,
            'capacity' => $capacity,
            'category' => $space['tag'],
            'rate' => $startingRate,
        ];
    });

    $categories = $spaceCatalog->pluck('category')->unique()->values();
@endphp

<section id="spaces-view" class="spaces-browser section-pad">
    <div class="section-wrap">
        <div class="spaces-browser__intro reveal">
            <p class="eyebrow">Browse Spaces</p>
            <h2 class="section-title">Browse our spaces</h2>
            <p class="section-copy">Explore HYVE rooms by setup, capacity, and use case, then jump straight into booking once you find your fit.</p>
        </div>

        <form class="spaces-filter reveal" data-spaces-filter>
            <label>
                <span>Search</span>
                <input type="text" placeholder="Search a space" data-space-search>
            </label>

            <label>
                <span>Category</span>
                <select data-space-category>
                    <option value="">Any</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category }}">{{ $category }}</option>
                    @endforeach
                </select>
            </label>

            <label>
                <span>Capacity</span>
                <select data-space-capacity>
                    <option value="">Any</option>
                    <option value="1-2">1 to 2 pax</option>
                    <option value="3-4">3 to 4 pax</option>
                    <option value="5+">5+ pax</option>
                </select>
            </label>

            <div class="spaces-filter__action">
                <button type="submit" class="button button--dark button--block">Search Spaces</button>
            </div>
        </form>

        <div class="spaces-browser__meta reveal">
            <p class="spaces-browser__count">Showing <span data-space-count>{{ $spaceCatalog->count() }}</span> of {{ $spaceCatalog->count() }} spaces</p>
        </div>

        <div class="spaces-browser__grid" data-space-grid>
            @foreach ($spaceCatalog as $space)
                <article
                    class="space-browser-card reveal"
                    data-space-card
                    data-space-title="{{ strtolower($space['title']) }}"
                    data-space-category="{{ strtolower($space['category']) }}"
                    data-space-capacity="{{ $space['capacity'] }}"
                >
                    <div class="space-browser-card__media">
                        <img src="{{ asset($space['image']) }}" alt="{{ $space['title'] }}">
                        <span class="space-browser-card__badge">{{ $space['category'] }}</span>
                    </div>

                    <div class="space-browser-card__body">
                        <div class="space-browser-card__head">
                            <div>
                                <h3>{{ $space['title'] }}</h3>
                                <p>{{ $space['capacity'] }} pax setup</p>
                            </div>
                            <span class="space-browser-card__index">0{{ $loop->iteration }}</span>
                        </div>

                        <p class="space-browser-card__copy">{{ $space['description'] }}</p>

                        <div class="chip-row chip-row--soft">
                            @foreach ($space['features'] as $feature)
                                <span>{{ $feature }}</span>
                            @endforeach
                        </div>

                        <div class="space-browser-card__foot">
                            <div>
                                <strong>{{ $space['rate'] }}</strong>
                                <small>starting rate</small>
                            </div>
                            <a href="{{ route('bookings.index', ['space' => \Illuminate\Support\Str::slug($space['title'])]) }}" class="space-browser-card__link">View details &amp; book</a>
                        </div>
                    </div>
                </article>
            @endforeach
        </div>
    </div>
</section>
