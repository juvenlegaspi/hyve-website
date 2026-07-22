@extends('layouts.app')

@push('head')
    <link rel="preload" as="image" href="{{ asset('images/optimized/common-area-1.webp') }}" type="image/webp" fetchpriority="high">
@endpush

@section('content')
    <div class="site-shell" data-home-shell data-page-mode="home">
        @include('partials.home.navigation')

        <main>
            <div data-home-only>
                @include('partials.home.overview')
            </div>
            <div data-home-only>
                @include('partials.home.services')
            </div>
            <div data-home-only>
                @include('partials.home.spaces')
            </div>
            <div data-home-only>
                @include('partials.home.rates')
            </div>
            <div data-home-only>
                @include('partials.home.amenities')
            </div>
            <div id="spaces-browser" class="hidden" data-spaces-browser>
                @include('partials.home.spaces-browser')
            </div>
            @include('partials.home.why-hyve')
            @include('partials.home.contact')
            <div data-home-only>
                @include('partials.home.booking-flow')
            </div>
        </main>
    </div>
@endsection
