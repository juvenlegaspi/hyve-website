@extends('layouts.auth')

@section('title', 'Register | HYVE Workspace')

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

            <div class="w-full max-w-[35rem] rounded-[1.6rem] bg-white shadow-[0_28px_80px_rgba(12,18,15,0.22)]">
                <div class="grid grid-cols-2 border-b border-[#163129]/10 text-center text-[1rem] font-semibold">
                    <a href="{{ route('login', ['return_to' => $returnTo]) }}" class="px-5 py-4 text-[#a3a3a3] transition hover:text-[#264f28]">Log in</a>
                    <a href="{{ route('register', ['return_to' => $returnTo]) }}" class="border-b-2 border-[#3f7b3d] px-5 py-4 text-[#264f28]">Register</a>
                </div>

                <div class="px-7 py-7 sm:px-8">
                    <h1 class="text-[1.7rem] font-semibold tracking-[-0.04em] text-[#1d1d1d]">Create your account</h1>
                    <p class="mt-1 text-[0.92rem] text-[#9a9a9a]">Register for your CourtSpace membership access</p>

                    @if ($errors->any())
                        <div class="mt-4 rounded-[1rem] border border-red-200 bg-red-50 px-4 py-3 text-[0.8rem] text-red-700">
                            @foreach ($errors->all() as $error)
                                <p>{{ $error }}</p>
                            @endforeach
                        </div>
                    @endif

                    <form action="{{ route('register.store') }}" method="POST" class="mt-6 grid gap-3.5 sm:grid-cols-2">
                        @csrf

                        <label class="block">
                            <span class="mb-2 block text-[0.78rem] font-semibold uppercase tracking-[0.12em] text-[#b1aba2]">Username</span>
                            <input type="text" name="username" value="{{ old('username') }}" class="w-full rounded-[0.95rem] border border-[#e6e0d7] bg-white px-4 py-3 text-[0.95rem] text-[#232323] outline-none transition focus:border-[#3f7b3d]" placeholder="courtspace01">
                        </label>

                        <label class="block">
                            <span class="mb-2 block text-[0.78rem] font-semibold uppercase tracking-[0.12em] text-[#b1aba2]">Phone number</span>
                            <input type="text" name="phone" value="{{ old('phone') }}" class="w-full rounded-[0.95rem] border border-[#e6e0d7] bg-white px-4 py-3 text-[0.95rem] text-[#232323] outline-none transition focus:border-[#3f7b3d]" placeholder="+63 9xx xxx xxxx">
                        </label>

                        <label class="block">
                            <span class="mb-2 block text-[0.78rem] font-semibold uppercase tracking-[0.12em] text-[#b1aba2]">First name</span>
                            <input type="text" name="first_name" value="{{ old('first_name') }}" class="w-full rounded-[0.95rem] border border-[#e6e0d7] bg-white px-4 py-3 text-[0.95rem] text-[#232323] outline-none transition focus:border-[#3f7b3d]" placeholder="Juan">
                        </label>

                        <label class="block">
                            <span class="mb-2 block text-[0.78rem] font-semibold uppercase tracking-[0.12em] text-[#b1aba2]">Last name</span>
                            <input type="text" name="last_name" value="{{ old('last_name') }}" class="w-full rounded-[0.95rem] border border-[#e6e0d7] bg-white px-4 py-3 text-[0.95rem] text-[#232323] outline-none transition focus:border-[#3f7b3d]" placeholder="Dela Cruz">
                        </label>

                        <label class="block sm:col-span-2">
                            <span class="mb-2 block text-[0.78rem] font-semibold uppercase tracking-[0.12em] text-[#b1aba2]">Email</span>
                            <input type="email" name="email" value="{{ old('email') }}" class="w-full rounded-[0.95rem] border border-[#e6e0d7] bg-white px-4 py-3 text-[0.95rem] text-[#232323] outline-none transition focus:border-[#3f7b3d]" placeholder="you@email.com">
                        </label>

                        <label class="block">
                            <span class="mb-2 block text-[0.78rem] font-semibold uppercase tracking-[0.12em] text-[#b1aba2]">Password</span>
                            <input type="password" name="password" class="w-full rounded-[0.95rem] border border-[#e6e0d7] bg-white px-4 py-3 text-[0.95rem] text-[#232323] outline-none transition focus:border-[#3f7b3d]" placeholder="At least 8 characters">
                        </label>

                        <label class="block">
                            <span class="mb-2 block text-[0.78rem] font-semibold uppercase tracking-[0.12em] text-[#b1aba2]">Confirm password</span>
                            <input type="password" name="password_confirmation" class="w-full rounded-[0.95rem] border border-[#e6e0d7] bg-white px-4 py-3 text-[0.95rem] text-[#232323] outline-none transition focus:border-[#3f7b3d]" placeholder="Repeat password">
                        </label>

                        <div class="sm:col-span-2">
                            <button type="submit" class="inline-flex w-full items-center justify-center rounded-full bg-[#3f7b3d] px-6 py-3 text-[0.96rem] font-semibold text-white transition hover:bg-[#346735]">
                                Create account
                            </button>
                        </div>
                    </form>

                    <p class="mt-6 text-center text-[0.92rem] text-[#a0a0a0]">
                        Already have an account?
                        <a href="{{ route('login', ['return_to' => $returnTo]) }}" class="font-semibold text-[#2f642f]">
                            Log in ->
                        </a>
                    </p>
                </div>
            </div>
        </div>
    </div>
@endsection
