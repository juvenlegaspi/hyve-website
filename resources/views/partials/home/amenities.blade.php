@php
    $amenityGallery = collect($spaces ?? [])->map(function (array $space): array {
        $gallery = collect($space['gallery'] ?? [$space['image'] ?? 'images/office.png'])
            ->filter()
            ->map(fn (string $image): string => asset($image))
            ->values()
            ->all();

        return [
            'title' => $space['title'] ?? 'HYVE Space',
            'tag' => $space['tag'] ?? 'Workspace',
            'description' => $space['description'] ?? 'A polished workspace experience at HYVE.',
            'features' => array_slice($space['features'] ?? [], 0, 3),
            'images' => $gallery !== [] ? $gallery : [asset('images/office.png')],
        ];
    })->values();
@endphp

<section id="amenities" class="section-pad">
    <div class="section-wrap">
        <div class="section-heading-block section-heading-block--split reveal">
            <div>
                <p class="eyebrow">Amenities</p>
                <h2 class="section-title">See the spaces and details that shape the HYVE experience.</h2>
            </div>
            <p class="section-copy section-copy--lead">Instead of a plain list, customers can preview the current space photos here and browse through the setup with simple previous and next controls.</p>
        </div>

        <div class="amenities-gallery reveal" data-amenities-gallery>
            <div class="amenities-gallery__header">
                <p class="amenities-gallery__label">Photo gallery</p>
                <div class="amenities-gallery__controls">
                    <button type="button" class="amenities-gallery__arrow" data-amenities-prev aria-label="Previous amenity photo">&#8249;</button>
                    <button type="button" class="amenities-gallery__arrow" data-amenities-next aria-label="Next amenity photo">&#8250;</button>
                </div>
            </div>

            <div class="amenities-gallery__viewport">
                @foreach ($amenityGallery as $space)
                    <article class="amenities-gallery__slide @if ($loop->first) is-active @endif" data-amenities-slide>
                        <div class="amenities-gallery__media">
                            <img
                                src="{{ $space['images'][0] }}"
                                alt="{{ $space['title'] }}"
                                data-amenities-main-image
                            >
                        </div>

                        <div class="amenities-gallery__body">
                            <div class="amenities-gallery__meta">
                                <span class="amenities-gallery__counter">{{ str_pad((string) ($loop->iteration), 2, '0', STR_PAD_LEFT) }} / {{ str_pad((string) $amenityGallery->count(), 2, '0', STR_PAD_LEFT) }}</span>
                                <span class="amenities-gallery__tag">{{ $space['tag'] }}</span>
                            </div>

                            <div>
                                <h3 class="amenities-gallery__title">{{ $space['title'] }}</h3>
                                <p class="amenities-gallery__copy">{{ $space['description'] }}</p>
                            </div>

                            @if ($space['features'] !== [])
                                <div class="amenities-gallery__chips">
                                    @foreach ($space['features'] as $feature)
                                        <span class="amenities-gallery__chip">{{ $feature }}</span>
                                    @endforeach
                                </div>
                            @endif

                            @if (count($space['images']) > 1)
                                <div class="amenities-gallery__thumbs">
                                    @foreach ($space['images'] as $image)
                                        <button
                                            type="button"
                                            class="amenities-gallery__thumb @if ($loop->first) is-active @endif"
                                            data-amenities-thumb
                                            data-image-src="{{ $image }}"
                                            aria-label="View {{ $space['title'] }} photo {{ $loop->iteration }}"
                                        >
                                            <img src="{{ $image }}" alt="{{ $space['title'] }} thumbnail {{ $loop->iteration }}">
                                        </button>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>
        </div>
    </div>
</section>
