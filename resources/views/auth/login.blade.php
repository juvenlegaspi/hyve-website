@extends('layouts.auth')

@section('title', 'Log In | HYVE Workspace')

@section('content')
    @php
        $returnTo = request('return_to') ?: url()->previous();
    @endphp

    <div class="relative min-h-screen overflow-hidden bg-[#f6f1e8]">
        <div class="absolute inset-0 bg-[url('{{ asset('images/room2.jpg') }}')] bg-cover bg-center opacity-55"></div>
        <div class="absolute inset-0 bg-[rgba(16,20,18,0.5)] backdrop-blur-[10px]"></div>

        <div class="relative flex min-h-screen items-center justify-center px-4 py-8">
            <a href="{{ $returnTo }}" class="absolute right-5 top-5 inline-flex h-10 w-10 items-center justify-center rounded-full bg-black/30 text-[1.7rem] leading-none text-white transition hover:bg-black/45">
                &times;
            </a>

            <div class="w-full max-w-[28.5rem] rounded-[1.6rem] bg-white shadow-[0_28px_80px_rgba(12,18,15,0.22)]">
                <div class="grid grid-cols-2 border-b border-[#163129]/10 text-center text-[1rem] font-semibold">
                    <a href="{{ route('login', ['return_to' => $returnTo]) }}" class="border-b-2 border-[#3f7b3d] px-5 py-4 text-[#264f28]">Log in</a>
                    <a href="{{ route('register', ['return_to' => $returnTo]) }}" class="px-5 py-4 text-[#a3a3a3] transition hover:text-[#264f28]">Register</a>
                </div>

                <div class="px-7 py-7 sm:px-8">
                    <h1 class="text-[1.75rem] font-semibold tracking-[-0.04em] text-[#1d1d1d]">Welcome back</h1>
                    <p class="mt-1 text-[0.92rem] text-[#9a9a9a]">Log in to your CourtSpace account</p>

                    @if ($errors->any())
                        <div class="mt-4 rounded-[1rem] border border-red-200 bg-red-50 px-4 py-3 text-[0.8rem] text-red-700">
                            @foreach ($errors->all() as $error)
                                <p>{{ $error }}</p>
                            @endforeach
                        </div>
                    @endif

                    <form action="{{ route('login.store') }}" method="POST" class="mt-6 space-y-4">
                        @csrf

                        <label class="block">
                            <span class="mb-2 block text-[0.78rem] font-semibold uppercase tracking-[0.12em] text-[#b1aba2]">Email</span>
                            <input
                                type="text"
                                name="login"
                                value="{{ old('login') }}"
                                class="w-full rounded-[0.95rem] border border-[#e6e0d7] bg-white px-4 py-3 text-[1rem] text-[#232323] outline-none transition focus:border-[#3f7b3d]"
                                placeholder="you@email.com"
                            >
                        </label>

                        <label class="block">
                            <span class="mb-2 block text-[0.78rem] font-semibold uppercase tracking-[0.12em] text-[#b1aba2]">Password</span>
                            <input
                                type="password"
                                name="password"
                                class="w-full rounded-[0.95rem] border border-[#e6e0d7] bg-white px-4 py-3 text-[1rem] text-[#232323] outline-none transition focus:border-[#3f7b3d]"
                                placeholder="........"
                            >
                        </label>

                        <button type="submit" class="inline-flex w-full items-center justify-center rounded-full bg-[#3f7b3d] px-6 py-3 text-[1rem] font-semibold text-white transition hover:bg-[#346735]">
                            Log in
                        </button>
                    </form>

                    <p class="mt-6 text-center text-[0.92rem] text-[#a0a0a0]">
                        No account yet?
                        <a href="{{ route('register', ['return_to' => $returnTo]) }}" class="font-semibold text-[#2f642f]">
                            Register for free ->
                        </a>
                    </p>
                </div>
            </div>
        </div>
    </div>
@endsection
