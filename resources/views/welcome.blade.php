@extends('layouts.app')

@section('content')
    <div class="relative overflow-x-hidden overflow-y-visible">
        <div class="pointer-events-none absolute inset-x-0 top-0 h-[38rem] bg-[radial-gradient(circle_at_top,_rgba(196,156,91,0.26),_transparent_58%)]"></div>
        <div class="pointer-events-none absolute left-[-8rem] top-[42rem] h-72 w-72 rounded-full bg-[#21453c]/10 blur-3xl"></div>
        <div class="pointer-events-none absolute right-[-10rem] top-[92rem] h-96 w-96 rounded-full bg-[#c49c5b]/12 blur-3xl"></div>

        @include('partials.home.navigation')

        <main class="relative">
            @include('partials.home.overview')
            @include('partials.home.services')
            @include('partials.home.rates')
            @include('partials.home.spaces')
            @include('partials.home.amenities')
            @include('partials.home.why-hyve')
            @include('partials.home.booking-flow')
            @include('partials.home.contact')
        </main>
    </div>
@endsection
