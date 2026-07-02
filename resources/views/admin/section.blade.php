@extends('layouts.admin')

@section('content')
    <section class="max-w-4xl">
        <p class="text-[0.68rem] font-bold uppercase tracking-[0.22em] text-[#b39a5a]">{{ $pageEyebrow }}</p>
        <h1 class="mt-2 text-[1.65rem] font-semibold tracking-[-0.05em] text-[#132320]">{{ $pageTitle }}</h1>
        <p class="mt-2 max-w-2xl text-[0.84rem] leading-6 text-[#817b70]">{{ $pageDescription }}</p>

        <div class="mt-6 rounded-[1.35rem] border border-dashed border-[#d4ddcb] bg-white/80 p-5 shadow-[0_18px_44px_rgba(17,28,24,0.05)]">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h2 class="text-[1.08rem] font-semibold text-[#173029]">Module placeholder is ready</h2>
                    <p class="mt-2 max-w-2xl text-[0.82rem] leading-6 text-[#8c867b]">
                        {{ $pageNote }}
                    </p>
                </div>

                <span class="inline-flex rounded-full bg-[#eef8df] px-3 py-1.5 text-[0.68rem] font-semibold text-[#315539]">
                    Clickable now
                </span>
            </div>

            <div class="mt-5 grid gap-3 md:grid-cols-3">
                <article class="rounded-[1rem] border border-[#e6ece0] bg-[#fbfcf8] p-3.5">
                    <p class="text-[0.66rem] font-bold uppercase tracking-[0.16em] text-[#a6a093]">Status</p>
                    <p class="mt-2 text-[0.94rem] font-semibold text-[#173029]">Waiting for content</p>
                    <p class="mt-2 text-[0.78rem] leading-6 text-[#8f887c]">Route and page are active. We only need to add the real tools next.</p>
                </article>

                <article class="rounded-[1rem] border border-[#e6ece0] bg-[#fbfcf8] p-3.5">
                    <p class="text-[0.66rem] font-bold uppercase tracking-[0.16em] text-[#a6a093]">Access</p>
                    <p class="mt-2 text-[0.94rem] font-semibold text-[#173029]">{{ $adminUser->isSuperAdmin() ? 'Super admin view' : 'Admin view' }}</p>
                    <p class="mt-2 text-[0.78rem] leading-6 text-[#8f887c]">This page already respects the admin access flow of the dashboard.</p>
                </article>

                
            </div>
        </div>
    </section>
@endsection
