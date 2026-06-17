@extends('layouts.auth')

@section('title', 'Register | HYVE Workspace')

@section('content')
    <div class="relative isolate overflow-hidden px-6 py-10 md:px-10">
        <div class="pointer-events-none absolute inset-x-0 top-0 h-[24rem] bg-[radial-gradient(circle_at_top,_rgba(196,156,91,0.24),_transparent_55%)]"></div>
        <div class="mx-auto max-w-5xl">
            <div class="mx-auto max-w-3xl rounded-[2rem] border border-white/70 bg-white/85 p-8 shadow-[0_30px_100px_rgba(18,24,21,0.08)] backdrop-blur md:p-10">
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

                <p class="text-sm font-semibold uppercase tracking-[0.28em] text-[#8c692c]">Monthly Membership</p>
                <h1 class="mt-4 font-display text-4xl tracking-[-0.04em] text-[#18130f]">Create your member account.</h1>
                <p class="mt-4 text-base leading-7 text-[#5f5449]">
                    Register only if you want a HYVE monthly-member account. For one-time bookings and regular space reservations, you can still submit a booking directly without creating an account.
                </p>

                @if ($errors->any())
                    <div class="mt-6 rounded-[1.5rem] border border-red-400/30 bg-red-500/10 p-5 text-sm leading-7 text-red-700">
                        <p class="font-semibold uppercase tracking-[0.18em] text-red-800">Please review your registration details.</p>
                        <ul class="mt-3 space-y-1">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form action="{{ route('register.store') }}" method="POST" class="mt-8 grid gap-5 sm:grid-cols-2">
                    @csrf
                    <label class="block">
                        <span class="mb-2 block text-xs font-semibold uppercase tracking-[0.2em] text-[#163129]">Username</span>
                        <input type="text" name="username" value="{{ old('username') }}" class="w-full rounded-2xl border border-[#163129]/12 bg-white px-4 py-3 text-[#18130f] outline-none transition focus:border-[#c49c5b]" placeholder="hyveclient01">
                        @error('username')
                            <span class="mt-2 block text-sm text-red-600">{{ $message }}</span>
                        @enderror
                    </label>

                    <label class="block">
                        <span class="mb-2 block text-xs font-semibold uppercase tracking-[0.2em] text-[#163129]">Phone Number</span>
                        <input type="text" name="phone" value="{{ old('phone') }}" class="w-full rounded-2xl border border-[#163129]/12 bg-white px-4 py-3 text-[#18130f] outline-none transition focus:border-[#c49c5b]" placeholder="+63 9xx xxx xxxx">
                        <span class="mt-2 block text-xs leading-6 text-[#74675a]">Use digits plus optional `+`, spaces, dashes, or parentheses.</span>
                        @error('phone')
                            <span class="mt-2 block text-sm text-red-600">{{ $message }}</span>
                        @enderror
                    </label>

                    <label class="block">
                        <span class="mb-2 block text-xs font-semibold uppercase tracking-[0.2em] text-[#163129]">First Name</span>
                        <input type="text" name="first_name" value="{{ old('first_name') }}" class="w-full rounded-2xl border border-[#163129]/12 bg-white px-4 py-3 text-[#18130f] outline-none transition focus:border-[#c49c5b]" placeholder="Juan">
                        @error('first_name')
                            <span class="mt-2 block text-sm text-red-600">{{ $message }}</span>
                        @enderror
                    </label>

                    <label class="block">
                        <span class="mb-2 block text-xs font-semibold uppercase tracking-[0.2em] text-[#163129]">Last Name</span>
                        <input type="text" name="last_name" value="{{ old('last_name') }}" class="w-full rounded-2xl border border-[#163129]/12 bg-white px-4 py-3 text-[#18130f] outline-none transition focus:border-[#c49c5b]" placeholder="Dela Cruz">
                        @error('last_name')
                            <span class="mt-2 block text-sm text-red-600">{{ $message }}</span>
                        @enderror
                    </label>

                    <label class="block sm:col-span-2">
                        <span class="mb-2 block text-xs font-semibold uppercase tracking-[0.2em] text-[#163129]">Email Address</span>
                        <input type="email" name="email" value="{{ old('email') }}" class="w-full rounded-2xl border border-[#163129]/12 bg-white px-4 py-3 text-[#18130f] outline-none transition focus:border-[#c49c5b]" placeholder="you@example.com">
                        @error('email')
                            <span class="mt-2 block text-sm text-red-600">{{ $message }}</span>
                        @enderror
                    </label>

                    <label class="block">
                        <span class="mb-2 block text-xs font-semibold uppercase tracking-[0.2em] text-[#163129]">Password</span>
                        <input type="password" name="password" class="w-full rounded-2xl border border-[#163129]/12 bg-white px-4 py-3 text-[#18130f] outline-none transition focus:border-[#c49c5b]" placeholder="At least 8 characters">
                        <span class="mt-2 block text-xs leading-6 text-[#74675a]">Use at least 8 characters with both letters and numbers.</span>
                        @error('password')
                            <span class="mt-2 block text-sm text-red-600">{{ $message }}</span>
                        @enderror
                    </label>

                    <label class="block">
                        <span class="mb-2 block text-xs font-semibold uppercase tracking-[0.2em] text-[#163129]">Confirm Password</span>
                        <input type="password" name="password_confirmation" class="w-full rounded-2xl border border-[#163129]/12 bg-white px-4 py-3 text-[#18130f] outline-none transition focus:border-[#c49c5b]" placeholder="Repeat your password">
                    </label>

                    <div class="sm:col-span-2">
                        <button type="submit" class="inline-flex w-full items-center justify-center rounded-full bg-[#163129] px-7 py-4 text-sm font-semibold uppercase tracking-[0.22em] text-white transition hover:bg-[#10241f]">
                            Create Account
                        </button>
                    </div>
                </form>

                <p class="mt-6 text-sm leading-7 text-[#5f5449]">
                    Just want to reserve a space without membership?
                    <a href="{{ route('bookings.index') }}" class="font-semibold text-[#163129] underline decoration-[#c49c5b] underline-offset-4">Book directly here</a>
                </p>

                <p class="mt-2 text-sm leading-7 text-[#5f5449]">
                    Already a monthly member?
                    <a href="{{ route('login') }}" class="font-semibold text-[#163129] underline decoration-[#c49c5b] underline-offset-4">Log in here</a>
                </p>
            </div>
        </div>
    </div>
@endsection
