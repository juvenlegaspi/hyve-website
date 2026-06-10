@extends('layouts.auth')

@section('title', 'Log In | HYVE Workspace')

@section('content')
    <div class="relative isolate overflow-hidden px-6 py-10 md:px-10">
        <div class="pointer-events-none absolute inset-x-0 top-0 h-[24rem] bg-[radial-gradient(circle_at_top,_rgba(196,156,91,0.24),_transparent_55%)]"></div>
        <div class="mx-auto max-w-5xl">
            <div class="mx-auto max-w-xl rounded-[2rem] border border-white/70 bg-white/85 p-8 shadow-[0_30px_100px_rgba(18,24,21,0.08)] backdrop-blur md:p-10">
                <div class="flex items-center justify-between gap-4">
                    <div class="flex items-center gap-3">
                        <img src="{{ asset('images/logohyve.jpg') }}" alt="HYVE logo" class="h-10 w-10 rounded-full border border-[#163129]/10 bg-white object-contain p-1">
                        <div>
                            <p class="text-sm font-semibold uppercase tracking-[0.3em] text-[#163129]">HYVE</p>
                            <p class="text-[10px] uppercase tracking-[0.22em] text-[#74675a]">Workspaces and Meetings</p>
                        </div>
                    </div>

                    <a href="{{ route('home') }}" class="rounded-full border border-[#163129]/12 px-4 py-2 text-[11px] font-semibold uppercase tracking-[0.18em] text-[#163129] transition hover:bg-white">
                        Back to Website
                    </a>
                </div>

                <p class="text-sm font-semibold uppercase tracking-[0.28em] text-[#8c692c]">Member Log In</p>
                <h1 class="mt-4 font-display text-4xl tracking-[-0.04em] text-[#18130f]">Welcome back, monthly member.</h1>
                <p class="mt-4 text-base leading-7 text-[#5f5449]">
                    Sign in with your username or email to access your monthly-member account, keep track of your details, and send repeat HYVE booking requests more easily.
                </p>

                <form action="{{ route('login.store') }}" method="POST" class="mt-8 space-y-5">
                    @csrf
                    <label class="block">
                        <span class="mb-2 block text-xs font-semibold uppercase tracking-[0.2em] text-[#163129]">Username or Email</span>
                        <input type="text" name="login" value="{{ old('login') }}" class="w-full rounded-2xl border border-[#163129]/12 bg-white px-4 py-3 text-[#18130f] outline-none transition focus:border-[#c49c5b]" placeholder="yourname or you@example.com">
                        @error('login')
                            <span class="mt-2 block text-sm text-red-600">{{ $message }}</span>
                        @enderror
                    </label>

                    <label class="block">
                        <span class="mb-2 block text-xs font-semibold uppercase tracking-[0.2em] text-[#163129]">Password</span>
                        <input type="password" name="password" class="w-full rounded-2xl border border-[#163129]/12 bg-white px-4 py-3 text-[#18130f] outline-none transition focus:border-[#c49c5b]" placeholder="Enter your password">
                        @error('password')
                            <span class="mt-2 block text-sm text-red-600">{{ $message }}</span>
                        @enderror
                    </label>

                    <button type="submit" class="inline-flex w-full items-center justify-center rounded-full bg-[#163129] px-7 py-4 text-sm font-semibold uppercase tracking-[0.22em] text-white transition hover:bg-[#10241f]">
                        Log In
                    </button>
                </form>

                <p class="mt-6 text-sm leading-7 text-[#5f5449]">
                    Booking for one-time or regular day use?
                    <a href="{{ route('bookings.index') }}" class="font-semibold text-[#163129] underline decoration-[#c49c5b] underline-offset-4">Book directly without logging in</a>
                </p>
            </div>
        </div>
    </div>
@endsection
