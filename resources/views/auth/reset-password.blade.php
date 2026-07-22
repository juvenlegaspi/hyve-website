@extends('layouts.auth')

@section('title', 'Reset Password | HYVE Workspace')

@section('content')
    <div class="relative min-h-screen overflow-hidden bg-[#f6f1e8]">
        <div class="absolute inset-0 bg-[url('{{ asset('images/room2.jpg') }}')] bg-cover bg-center opacity-55"></div>
        <div class="absolute inset-0 bg-[rgba(16,20,18,0.5)] backdrop-blur-[10px]"></div>

        <div class="relative flex min-h-screen items-center justify-center px-4 py-8">
            <a href="{{ route('login') }}" class="absolute right-5 top-5 inline-flex h-10 w-10 items-center justify-center rounded-full bg-black/30 text-[1.7rem] leading-none text-white transition hover:bg-black/45" aria-label="Back to login">
                &times;
            </a>

            <div class="w-full max-w-[30rem] rounded-[1.6rem] bg-white shadow-[0_28px_80px_rgba(12,18,15,0.22)]">
                <div class="px-7 py-8 sm:px-8">
                    <p class="text-[0.76rem] font-semibold uppercase tracking-[0.16em] text-[#3f7b3d]">Secure account recovery</p>
                    <h1 class="mt-2 text-[1.75rem] font-semibold tracking-[-0.04em] text-[#1d1d1d]">Create a new password</h1>
                    <p class="mt-2 text-[0.92rem] leading-6 text-[#858585]">Choose at least eight characters with both letters and numbers.</p>

                    @if ($errors->any())
                        <div class="mt-5 rounded-[1rem] border border-red-200 bg-red-50 px-4 py-3 text-[0.82rem] text-red-700">
                            @foreach ($errors->all() as $error)
                                <p>{{ $error }}</p>
                            @endforeach
                        </div>
                    @endif

                    <form action="{{ route('password.store') }}" method="POST" class="mt-6 space-y-4">
                        @csrf
                        <input type="hidden" name="token" value="{{ $token }}">

                        <label class="block">
                            <span class="mb-2 block text-[0.78rem] font-semibold uppercase tracking-[0.12em] text-[#b1aba2]">Email</span>
                            <input
                                type="email"
                                name="email"
                                value="{{ old('email', $email) }}"
                                class="w-full rounded-[0.95rem] border border-[#e6e0d7] bg-white px-4 py-3 text-[1rem] text-[#232323] outline-none transition focus:border-[#3f7b3d]"
                                autocomplete="email"
                                required
                                autofocus
                            >
                        </label>

                        <label class="block">
                            <span class="mb-2 block text-[0.78rem] font-semibold uppercase tracking-[0.12em] text-[#b1aba2]">New password</span>
                            <input
                                type="password"
                                name="password"
                                class="w-full rounded-[0.95rem] border border-[#e6e0d7] bg-white px-4 py-3 text-[1rem] text-[#232323] outline-none transition focus:border-[#3f7b3d]"
                                placeholder="At least 8 characters"
                                autocomplete="new-password"
                                required
                            >
                        </label>

                        <label class="block">
                            <span class="mb-2 block text-[0.78rem] font-semibold uppercase tracking-[0.12em] text-[#b1aba2]">Confirm new password</span>
                            <input
                                type="password"
                                name="password_confirmation"
                                class="w-full rounded-[0.95rem] border border-[#e6e0d7] bg-white px-4 py-3 text-[1rem] text-[#232323] outline-none transition focus:border-[#3f7b3d]"
                                placeholder="Repeat your new password"
                                autocomplete="new-password"
                                required
                            >
                        </label>

                        <button type="submit" class="inline-flex w-full items-center justify-center rounded-full bg-[#3f7b3d] px-6 py-3 text-[1rem] font-semibold text-white transition hover:bg-[#346735]">
                            Reset password
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
