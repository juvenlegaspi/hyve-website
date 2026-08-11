@extends('layouts.admin')

@section('content')
    <div class="mx-auto w-full max-w-[1180px] p-5 lg:p-8">
        @if (session('admin_success'))
            <div class="mb-4 rounded-2xl border border-[#cfe2c1] bg-[#f1f8e9] px-4 py-3 text-sm font-semibold text-[#356039]">{{ session('admin_success') }}</div>
        @endif

        @if ($errors->any())
            <div class="mb-4 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                @foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach
            </div>
        @endif

        <header class="mb-6 flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="text-xs font-black uppercase tracking-[0.2em] text-[#b68a3d]">Member communication</p>
                <h1 class="mt-2 text-3xl font-semibold tracking-[-0.04em] text-[#142d25]">Announcements</h1>
                <p class="mt-1 text-sm text-[#7d857b]">Publish notices that appear in every member's HYVE dashboard and notification badge.</p>
            </div>
        </header>

        <div class="grid items-start gap-5 xl:grid-cols-[minmax(20rem,0.72fr)_minmax(0,1.28fr)]">
            <section class="rounded-[1.4rem] border border-[#dfe6db] bg-white p-5 shadow-[0_18px_45px_rgba(20,38,31,0.06)]">
                <h2 class="text-lg font-bold text-[#18362d]">Create announcement</h2>
                <p class="mt-1 text-xs leading-5 text-[#8b9188]">Set the publication period and urgency. Members receive their own unread notification.</p>

                <form method="POST" action="{{ route('admin.announcements.store') }}" class="mt-5 grid gap-4">
                    @csrf
                    <label class="grid gap-1.5 text-xs font-bold uppercase tracking-[0.1em] text-[#8c8a7e]">
                        Title
                        <input name="title" value="{{ old('title') }}" maxlength="160" required class="rounded-xl border border-[#dfe5da] px-3.5 py-3 text-sm font-normal normal-case tracking-normal text-[#20372f] outline-none focus:border-[#5b874a]">
                    </label>
                    <label class="grid gap-1.5 text-xs font-bold uppercase tracking-[0.1em] text-[#8c8a7e]">
                        Message
                        <textarea name="body" rows="6" maxlength="3000" required class="rounded-xl border border-[#dfe5da] px-3.5 py-3 text-sm font-normal normal-case tracking-normal text-[#20372f] outline-none focus:border-[#5b874a]">{{ old('body') }}</textarea>
                    </label>
                    <label class="grid gap-1.5 text-xs font-bold uppercase tracking-[0.1em] text-[#8c8a7e]">
                        Priority
                        <select name="priority" class="rounded-xl border border-[#dfe5da] px-3.5 py-3 text-sm font-normal normal-case tracking-normal text-[#20372f]">
                            <option value="info">Information</option>
                            <option value="important">Important</option>
                            <option value="urgent">Urgent</option>
                        </select>
                    </label>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <label class="grid gap-1.5 text-xs font-bold uppercase tracking-[0.1em] text-[#8c8a7e]">Publish at
                            <input type="datetime-local" name="published_at" value="{{ old('published_at', now()->format('Y-m-d\TH:i')) }}" required class="rounded-xl border border-[#dfe5da] px-3 py-3 text-xs font-normal normal-case tracking-normal">
                        </label>
                        <label class="grid gap-1.5 text-xs font-bold uppercase tracking-[0.1em] text-[#8c8a7e]">Expires at
                            <input type="datetime-local" name="expires_at" value="{{ old('expires_at') }}" class="rounded-xl border border-[#dfe5da] px-3 py-3 text-xs font-normal normal-case tracking-normal">
                        </label>
                    </div>
                    <label class="flex items-center gap-2 rounded-xl bg-[#f6f8f2] px-3.5 py-3 text-sm font-semibold text-[#425b4c]">
                        <input type="checkbox" name="is_active" value="1" checked> Active and visible when published
                    </label>
                    <button class="rounded-xl bg-[#3f7b3d] px-4 py-3 text-sm font-bold text-white transition hover:bg-[#346735]">Publish announcement</button>
                </form>
            </section>

            <section class="grid gap-3">
                @forelse ($announcements as $announcement)
                    <article class="rounded-[1.35rem] border border-[#dfe6db] bg-white p-5 shadow-[0_16px_38px_rgba(20,38,31,0.05)]">
                        <form method="POST" action="{{ route('admin.announcements.update', $announcement) }}" class="grid gap-3">
                            @csrf
                            @method('PATCH')
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <span class="rounded-full px-2.5 py-1 text-[0.65rem] font-black uppercase tracking-[0.1em] @if($announcement->priority === 'urgent') bg-red-100 text-red-700 @elseif($announcement->priority === 'important') bg-amber-100 text-amber-700 @else bg-[#edf5df] text-[#3e6b42] @endif">{{ $announcement->priority }}</span>
                                <span class="text-xs text-[#8b9389]">Read by {{ $announcement->reads_count }} member(s)</span>
                            </div>
                            <input name="title" value="{{ $announcement->title }}" maxlength="160" required class="rounded-xl border border-[#e0e5dc] px-3.5 py-2.5 text-base font-bold text-[#1f3930]">
                            <textarea name="body" rows="4" maxlength="3000" required class="rounded-xl border border-[#e0e5dc] px-3.5 py-3 text-sm leading-6 text-[#667168]">{{ $announcement->body }}</textarea>
                            <div class="grid gap-3 md:grid-cols-3">
                                <select name="priority" class="rounded-xl border border-[#e0e5dc] px-3 py-2.5 text-sm">
                                    @foreach (['info' => 'Information', 'important' => 'Important', 'urgent' => 'Urgent'] as $value => $label)
                                        <option value="{{ $value }}" @selected($announcement->priority === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                <input type="datetime-local" name="published_at" value="{{ $announcement->published_at?->format('Y-m-d\TH:i') }}" required class="rounded-xl border border-[#e0e5dc] px-3 py-2.5 text-xs">
                                <input type="datetime-local" name="expires_at" value="{{ $announcement->expires_at?->format('Y-m-d\TH:i') }}" class="rounded-xl border border-[#e0e5dc] px-3 py-2.5 text-xs">
                            </div>
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <label class="flex items-center gap-2 text-sm font-semibold text-[#52635a]"><input type="checkbox" name="is_active" value="1" @checked($announcement->is_active)> Active</label>
                                <button class="rounded-xl border border-[#cbd9c4] px-4 py-2 text-xs font-bold text-[#376042]">Save changes</button>
                            </div>
                        </form>
                        <form method="POST" action="{{ route('admin.announcements.destroy', $announcement) }}" class="mt-3 border-t border-[#edf0ea] pt-3" onsubmit="return confirm('Delete this announcement for all members?');">
                            @csrf
                            @method('DELETE')
                            <button class="text-xs font-bold text-red-600">Delete announcement</button>
                        </form>
                    </article>
                @empty
                    <div class="rounded-[1.35rem] border border-dashed border-[#d7dfd2] bg-white p-8 text-center text-sm text-[#7c887e]">No announcements yet.</div>
                @endforelse

                {{ $announcements->links() }}
            </section>
        </div>
    </div>
@endsection
