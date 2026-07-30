@extends('layouts.auth')

@section('title', 'Owner Log In | HYVE')

@section('content')
    <div class="relative min-h-screen overflow-hidden bg-[#efe9dc]">
        <div class="absolute inset-0 bg-[url('{{ asset('images/room2.jpg') }}')] bg-cover bg-center opacity-40"></div>
        <div class="absolute inset-0 bg-[linear-gradient(135deg,rgba(13,39,28,.93),rgba(33,71,48,.82))] backdrop-blur-[10px]"></div>

        <div class="relative flex min-h-screen items-center justify-center px-4 py-8">
            <a href="{{ route('home') }}" class="absolute right-5 top-5 inline-flex h-10 w-10 items-center justify-center rounded-full bg-black/25 text-[1.7rem] leading-none text-white transition hover:bg-black/40">
                &times;
            </a>

            <div class="w-full max-w-[29rem] overflow-hidden rounded-[1.7rem] bg-white shadow-[0_30px_90px_rgba(4,18,11,.32)]">
                <div class="bg-[linear-gradient(125deg,#163b29,#315e3d)] px-7 py-7 text-center text-white sm:px-8">
                    <p class="text-[0.72rem] font-bold uppercase tracking-[0.24em] text-[#d1b674]">Private executive access</p>
                    <h1 class="mt-3 text-[1.9rem] font-semibold tracking-[-0.045em]">HYVE Owner Portal</h1>
                    <p class="mt-2 text-[0.86rem] leading-relaxed text-[#dbe7dc]">
                        Secure read-only access to the dashboard, live sales monitoring, and reports.
                    </p>
                </div>

                <div class="px-7 py-7 sm:px-8">
                    @if ($errors->any())
                        <div class="mb-5 rounded-[1rem] border border-red-200 bg-red-50 px-4 py-3 text-[0.8rem] text-red-700">
                            @foreach ($errors->all() as $error)
                                <p>{{ $error }}</p>
                            @endforeach
                        </div>
                    @endif

                    <form action="{{ route('owner.login.store') }}" method="POST" class="space-y-4">
                        @csrf

                        <label class="block">
                            <span class="mb-2 block text-[0.76rem] font-semibold uppercase tracking-[0.12em] text-[#aaa398]">Owner email or username</span>
                            <input
                                type="text"
                                name="login"
                                value="{{ old('login') }}"
                                class="w-full rounded-[0.95rem] border border-[#e3ded4] bg-white px-4 py-3 text-[1rem] text-[#232323] outline-none transition focus:border-[#3f7b3d] focus:ring-4 focus:ring-[#3f7b3d]/10"
                                placeholder="owner@hyve.com"
                                autocomplete="username"
                                required
                                autofocus
                            >
                        </label>

                        <label class="block">
                            <span class="mb-2 flex items-center justify-between gap-3 text-[0.76rem] font-semibold uppercase tracking-[0.12em] text-[#aaa398]">
                                <span>Password</span>
                                <a href="{{ route('password.request') }}" class="normal-case tracking-normal text-[#3f7b3d]">Forgot password?</a>
                            </span>
                            <input
                                type="password"
                                name="password"
                                class="w-full rounded-[0.95rem] border border-[#e3ded4] bg-white px-4 py-3 text-[1rem] text-[#232323] outline-none transition focus:border-[#3f7b3d] focus:ring-4 focus:ring-[#3f7b3d]/10"
                                placeholder="••••••••"
                                autocomplete="current-password"
                                required
                            >
                        </label>

                        <label class="flex items-center gap-2 text-[0.78rem] text-[#7a7f79]">
                            <input type="checkbox" name="remember" value="1" class="rounded border-[#d7ddd2] text-[#315e3d] focus:ring-[#315e3d]">
                            Keep me signed in on this device
                        </label>

                        <button type="submit" class="inline-flex w-full items-center justify-center rounded-full bg-[#315e3d] px-6 py-3 text-[1rem] font-semibold text-white transition hover:bg-[#244d32]">
                            Enter Owner Portal
                        </button>
                    </form>

                    <p class="mt-6 text-center text-[0.74rem] leading-relaxed text-[#9a9f98]">
                        This portal accepts the single authorized HYVE Owner account only.
                    </p>
                </div>
            </div>
        </div>
    </div>
@endsection
