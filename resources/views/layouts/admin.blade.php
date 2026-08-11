<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $meta['title'] ?? 'HYVE Admin' }}</title>
    <meta name="description" content="{{ $meta['description'] ?? 'HYVE admin dashboard' }}">
    <link rel="icon" type="image/jpeg" href="{{ asset('images/logohyve.jpg') }}">
    <link rel="shortcut icon" href="{{ asset('images/logohyve.jpg') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/logohyve.jpg') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-[#f7f5ef] text-[#153027] antialiased">
    @php
        $sidebarSections = $sidebarSections ?? (function () use ($adminUser) {
            return collect(config('admin_permissions.sidebar_sections', []))
                ->map(function (array $section) use ($adminUser): array {
                    $section['items'] = collect($section['items'])
                        ->filter(fn (array $item): bool => $adminUser->hasPermission((string) ($item['permission'] ?? '')))
                        ->values()
                        ->all();

                    return $section;
                })
                ->filter(fn (array $section): bool => $section['items'] !== [])
                ->values()
                ->all();
        })();

        $sidebarIcons = [
            'Dashboard' => 'grid',
            'Rooms' => 'grid',
            'Room Schedule' => 'calendar',
            'Calendar & Events' => 'calendar-plus',
            'Pricing Rules' => 'tag',
            'Bookings' => 'calendar-check',
            'Payments' => 'card',
            'Messages' => 'chat',
            'Announcements' => 'bell',
            'Sales Monitoring' => 'chart',
            'Users' => 'users',
            'Reports' => 'chart',
            'Admin Roles' => 'shield',
            'Settings' => 'gear',
        ];
        $isOwnerPortal = $adminUser->isOwner();
        $panelLabel = $isOwnerPortal ? 'Owner Portal' : 'Admin';
        $adminMessageUnreadCount = $adminUser->hasPermission('messages.view')
            ? \App\Models\SupportConversation::query()->withUnreadForAdmin()->count()
            : 0;
        $adminOnlineBookingUnreadCount = $adminUser->hasPermission('bookings.view') && \Illuminate\Support\Facades\Schema::hasTable('booking_activities')
            ? \App\Models\BookingActivity::query()
                ->where('event_key', 'booking_submitted')
                ->whereNull('read_at')
                ->whereHas('bookingHeader', fn ($query) => $query->where('source', \App\Models\BookingHeader::SOURCE_WEB))
                ->distinct()
                ->count('booking_header_id')
            : 0;
    @endphp

    <div class="flex min-h-screen">
        <aside class="max-lg:hidden sticky top-0 h-screen w-[14.3rem] shrink-0 border-r border-[#dfe5db] bg-white lg:flex lg:flex-col">
            <div class="border-b border-[#edf1ea] px-4 py-6">
                <a href="{{ route('admin.dashboard') }}" class="flex items-baseline gap-2">
                    <span class="text-[1.18rem] font-black tracking-[-0.04em] text-black">HYVE</span>
                    <span class="text-[0.78rem] font-medium text-black">{{ $panelLabel }}</span>
                </a>
            </div>

            <nav
                class="flex-1 overflow-y-auto px-0 py-2"
                @if($adminUser->hasPermission('messages.view')) data-admin-message-badge-root data-unread-url="{{ route('admin.messages.unread') }}" data-messages-url="{{ route('admin.messages.index') }}" @endif
                @if($adminUser->hasPermission('bookings.view')) data-admin-booking-badge-root data-booking-unread-url="{{ route('admin.bookings.online-unread') }}" data-bookings-url="{{ route('admin.bookings.index') }}" @endif
            >
                @foreach (($sidebarSections ?? []) as $section)
                    @if (! empty($section['title']))
                        <p class="px-4 pb-2 pt-4 text-[0.7rem] font-bold uppercase tracking-[0.18em] text-[#d0d3cb]">
                            {{ $section['title'] }}
                        </p>
                    @endif

                    <div class="grid gap-1">
                        @foreach ($section['items'] as $item)
                            @php($icon = $sidebarIcons[$item['label']] ?? 'grid')
                            @php($isActiveItem = request()->routeIs($item['route']) || ($item['label'] === 'Messages' && request()->routeIs('admin.messages.*')))
                            <a
                                href="{{ route($item['route']) }}"
                                class="@if ($isActiveItem) border-r-[3px] border-[#5e8b43] bg-[#edf5df] text-[#224133] @else text-[#69736a] hover:bg-[#fafbf7] @endif flex items-center gap-3 px-4 py-2.5 text-[0.8rem] font-medium transition"
                            >
                                <span class="inline-flex h-4 w-4 items-center justify-center text-[#8b9387]">
                                    @if ($icon === 'grid')
                                        <svg viewBox="0 0 16 16" class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="1.6">
                                            <rect x="2.25" y="2.25" width="4.5" height="4.5" rx="1"></rect>
                                            <rect x="9.25" y="2.25" width="4.5" height="4.5" rx="1"></rect>
                                            <rect x="2.25" y="9.25" width="4.5" height="4.5" rx="1"></rect>
                                            <rect x="9.25" y="9.25" width="4.5" height="4.5" rx="1"></rect>
                                        </svg>
                                    @elseif ($icon === 'calendar')
                                        <svg viewBox="0 0 16 16" class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="1.6">
                                            <rect x="2.25" y="3.25" width="11.5" height="10.5" rx="1.5"></rect>
                                            <path d="M5 1.75v3M11 1.75v3M2.25 6.25h11.5"></path>
                                        </svg>
                                    @elseif ($icon === 'calendar-plus')
                                        <svg viewBox="0 0 16 16" class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="1.6">
                                            <rect x="2.25" y="3.25" width="11.5" height="10.5" rx="1.5"></rect>
                                            <path d="M5 1.75v3M11 1.75v3M2.25 6.25h11.5M8 8.2v3.6M6.2 10h3.6"></path>
                                        </svg>
                                    @elseif ($icon === 'tag')
                                        <svg viewBox="0 0 16 16" class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="1.6">
                                            <path d="M8.5 2.25H4.75a1.5 1.5 0 0 0-1.5 1.5V7.5l5 5 5.5-5.5-5.25-4.75Z"></path>
                                            <circle cx="5.5" cy="5.5" r="0.9" fill="currentColor" stroke="none"></circle>
                                        </svg>
                                    @elseif ($icon === 'calendar-check')
                                        <svg viewBox="0 0 16 16" class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="1.6">
                                            <rect x="2.25" y="3.25" width="11.5" height="10.5" rx="1.5"></rect>
                                            <path d="M5 1.75v3M11 1.75v3M2.25 6.25h11.5M5.8 10l1.4 1.4L10.4 8.4"></path>
                                        </svg>
                                    @elseif ($icon === 'card')
                                        <svg viewBox="0 0 16 16" class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="1.6">
                                            <rect x="1.75" y="3" width="12.5" height="10" rx="1.75"></rect>
                                            <path d="M1.75 6h12.5M4.5 10.25h2.5"></path>
                                        </svg>
                                    @elseif ($icon === 'chat')
                                        <svg viewBox="0 0 16 16" class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="1.6">
                                            <path d="M2.25 2.75h11.5v8H7l-3.75 2.5v-2.5h-1Z"></path>
                                            <path d="M5 6.25h6M5 8.5h4"></path>
                                        </svg>
                                    @elseif ($icon === 'bell')
                                        <svg viewBox="0 0 16 16" class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="1.6">
                                            <path d="M3.5 11.5h9l-1.1-1.7V6.9A3.4 3.4 0 0 0 8 3.5a3.4 3.4 0 0 0-3.4 3.4v2.9L3.5 11.5Z"></path>
                                            <path d="M6.5 12.2a1.5 1.5 0 0 0 3 0M8 1.8v1.4"></path>
                                        </svg>
                                    @elseif ($icon === 'users')
                                        <svg viewBox="0 0 16 16" class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="1.6">
                                            <circle cx="6" cy="5.5" r="2"></circle>
                                            <path d="M2.8 12.5c.35-1.8 1.6-2.9 3.2-2.9s2.85 1.1 3.2 2.9M11 7a1.7 1.7 0 1 0 0-3.4M11 9.9c1.1.2 1.95 1 2.25 2.1"></path>
                                        </svg>
                                    @elseif ($icon === 'coin')
                                        <svg viewBox="0 0 16 16" class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="1.6">
                                            <circle cx="8" cy="8" r="5.75"></circle>
                                            <path d="M9.7 6.1c-.3-.5-.9-.85-1.7-.85-.95 0-1.65.45-1.65 1.2 0 .7.6 1 1.55 1.2l.55.1c1.3.25 2.1.8 2.1 1.95 0 1.25-1.05 2.1-2.6 2.1-1.15 0-2.05-.4-2.55-1.2M8 4.5v7"></path>
                                        </svg>
                                    @elseif ($icon === 'box')
                                        <svg viewBox="0 0 16 16" class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="1.6">
                                            <path d="M8 2.2 13 4.8v6.4L8 13.8 3 11.2V4.8L8 2.2Z"></path>
                                            <path d="M3.2 4.9 8 7.4l4.8-2.5M8 7.4v6.2"></path>
                                        </svg>
                                    @elseif ($icon === 'bag')
                                        <svg viewBox="0 0 16 16" class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="1.6">
                                            <path d="M3.25 5.25h9.5l-.6 7a1.5 1.5 0 0 1-1.5 1.37h-5.3a1.5 1.5 0 0 1-1.5-1.37l-.6-7Z"></path>
                                            <path d="M5.5 6V4.9a2.5 2.5 0 0 1 5 0V6"></path>
                                        </svg>
                                    @elseif ($icon === 'chart')
                                        <svg viewBox="0 0 16 16" class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="1.6">
                                            <path d="M2.5 13.25h11"></path>
                                            <path d="M4 11V7.8M8 11V4.8M12 11V6.3"></path>
                                        </svg>
                                    @elseif ($icon === 'shield')
                                        <svg viewBox="0 0 16 16" class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="1.6">
                                            <path d="M8 2.1 12.5 3.8v3.4c0 2.8-1.7 4.9-4.5 6.4-2.8-1.5-4.5-3.6-4.5-6.4V3.8L8 2.1Z"></path>
                                            <path d="M6.1 8.1 7.4 9.4l2.5-2.5"></path>
                                        </svg>
                                    @elseif ($icon === 'gear')
                                        <svg viewBox="0 0 16 16" class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="1.6">
                                            <circle cx="8" cy="8" r="2.1"></circle>
                                            <path d="M8 2.2v1.4M8 12.4v1.4M13.8 8h-1.4M3.6 8H2.2M12.1 3.9l-1 1M4.9 11.1l-1 1M12.1 12.1l-1-1M4.9 4.9l-1-1"></path>
                                        </svg>
                                    @endif
                                </span>
                                <span class="flex-1">{{ $item['label'] }}</span>
                                @if ($item['label'] === 'Bookings')
                                    <span class="{{ $adminOnlineBookingUnreadCount > 0 ? '' : 'hidden' }} min-w-5 rounded-full bg-[#dc3f36] px-1.5 py-0.5 text-center text-[0.62rem] font-bold text-white shadow-[0_0_0_2px_rgba(220,63,54,0.14)]" data-admin-booking-badge aria-label="{{ $adminOnlineBookingUnreadCount }} new online bookings">{{ $adminOnlineBookingUnreadCount }}</span>
                                @endif
                                @if ($item['label'] === 'Messages')
                                    <span class="{{ $adminMessageUnreadCount > 0 ? '' : 'hidden' }} min-w-5 rounded-full bg-[#dc3f36] px-1.5 py-0.5 text-center text-[0.62rem] font-bold text-white shadow-[0_0_0_2px_rgba(220,63,54,0.14)]" data-admin-message-badge aria-label="{{ $adminMessageUnreadCount }} unread customer conversations">{{ $adminMessageUnreadCount }}</span>
                                @endif
                            </a>
                        @endforeach
                    </div>
                @endforeach
            </nav>

            <div class="mt-auto border-t border-[#edf1ea] px-3 py-3">
                <div class="flex items-center gap-2.5 rounded-[0.9rem] bg-[#fbfcf8] px-3 py-2">
                    <div class="flex h-8 w-8 items-center justify-center rounded-full bg-[#eef5de] text-[0.76rem] font-black text-[#2e6c42]">
                        {{ strtoupper(substr((string) ($adminUser->first_name ?? 'A'), 0, 1)) }}
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-[0.78rem] font-semibold text-[#163128]">{{ $adminUser->name ?? 'Admin' }}</p>
                        <p class="truncate text-[0.68rem] text-[#8c9682]">{{ $isOwnerPortal ? 'HYVE Owner · Read only' : ($adminUser->email ?? str_replace('_', ' ', (string) ($adminUser->role ?? 'admin'))) }}</p>
                    </div>
                    <form action="{{ route('logout') }}" method="POST">
                        @csrf
                        <button type="submit" class="flex h-7 w-7 items-center justify-center rounded-[0.75rem] border border-[#e1e7d9] bg-white text-[#6a7569] transition hover:bg-[#f7f9f4]">
                            <svg viewBox="0 0 16 16" class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="1.6">
                                <path d="M6 2.5H3.75a1.25 1.25 0 0 0-1.25 1.25v8.5a1.25 1.25 0 0 0 1.25 1.25H6"></path>
                                <path d="M9 11.5 12.5 8 9 4.5M12 8H5.5"></path>
                            </svg>
                        </button>
                    </form>
                </div>
            </div>
        </aside>

        <div class="hidden lg:hidden" data-admin-mobile-nav aria-hidden="true">
            <button
                type="button"
                class="fixed inset-0 z-[10030] bg-[#10251f]/55 backdrop-blur-[2px]"
                data-admin-mobile-nav-close
                aria-label="Close admin menu"
            ></button>
            <aside class="fixed inset-y-0 left-0 z-[10040] flex w-[min(19rem,88vw)] flex-col bg-white shadow-2xl" role="dialog" aria-modal="true" aria-label="Admin navigation">
                <div class="flex items-center justify-between border-b border-[#edf1ea] px-4 py-4">
                    <a href="{{ route('admin.dashboard') }}" class="flex items-baseline gap-2">
                        <span class="text-[1.18rem] font-black tracking-[-0.04em] text-black">HYVE</span>
                        <span class="text-[0.78rem] font-medium text-black">{{ $panelLabel }}</span>
                    </a>
                    <button type="button" class="flex h-10 w-10 items-center justify-center rounded-full border border-[#dce4d8] text-xl text-[#385346]" data-admin-mobile-nav-close aria-label="Close admin menu">&times;</button>
                </div>

                <nav class="flex-1 overflow-y-auto px-3 py-3">
                    @foreach (($sidebarSections ?? []) as $section)
                        @if (! empty($section['title']))
                            <p class="px-2 pb-1.5 pt-4 text-[0.68rem] font-bold uppercase tracking-[0.18em] text-[#b5bbb0]">{{ $section['title'] }}</p>
                        @endif
                        <div class="grid gap-1">
                            @foreach ($section['items'] as $item)
                                @php($isActiveMobileItem = request()->routeIs($item['route']) || ($item['label'] === 'Messages' && request()->routeIs('admin.messages.*')))
                                <a href="{{ route($item['route']) }}" class="@if ($isActiveMobileItem) bg-[#edf5df] text-[#224133] @else text-[#626e65] @endif flex min-h-11 items-center gap-3 rounded-xl px-3 py-2.5 text-[0.82rem] font-semibold">
                                    <span class="h-2 w-2 shrink-0 rounded-full @if ($isActiveMobileItem) bg-[#5e8b43] @else bg-[#cbd2c7] @endif"></span>
                                    <span class="flex-1">{{ $item['label'] }}</span>
                                    @if ($item['label'] === 'Bookings')
                                        <span class="{{ $adminOnlineBookingUnreadCount > 0 ? '' : 'hidden' }} min-w-5 rounded-full bg-[#dc3f36] px-1.5 py-0.5 text-center text-[0.62rem] font-bold text-white" data-admin-booking-badge>{{ $adminOnlineBookingUnreadCount }}</span>
                                    @endif
                                    @if ($item['label'] === 'Messages')
                                        <span class="{{ $adminMessageUnreadCount > 0 ? '' : 'hidden' }} min-w-5 rounded-full bg-[#dc3f36] px-1.5 py-0.5 text-center text-[0.62rem] font-bold text-white" data-admin-message-badge>{{ $adminMessageUnreadCount }}</span>
                                    @endif
                                </a>
                            @endforeach
                        </div>
                    @endforeach
                </nav>

                <div class="border-t border-[#edf1ea] p-3">
                    <div class="mb-2 rounded-xl bg-[#f7f9f4] px-3 py-2">
                        <strong class="block truncate text-[0.78rem] text-[#19352c]">{{ $adminUser->name ?? 'Admin' }}</strong>
                        <span class="block truncate text-[0.66rem] text-[#899286]">{{ $adminUser->email ?? str_replace('_', ' ', (string) ($adminUser->role ?? 'admin')) }}</span>
                    </div>
                    <form action="{{ route('logout') }}" method="POST">
                        @csrf
                        <button type="submit" class="w-full rounded-xl border border-[#dbe5d1] px-3 py-2.5 text-[0.76rem] font-semibold text-[#48624f]">Log out</button>
                    </form>
                </div>
            </aside>
        </div>

        <main class="min-w-0 flex-1">
            <div class="border-b border-[#e6eadf] bg-white px-4 py-4 lg:hidden">
                <div class="flex items-center justify-between gap-3">
                    <button type="button" class="flex h-10 w-10 items-center justify-center rounded-full border border-[#dbe5d1] text-[#365448]" data-admin-mobile-nav-open aria-label="Open admin menu" aria-expanded="false">
                        <svg viewBox="0 0 20 20" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round">
                            <path d="M3 5.5h14M3 10h14M3 14.5h14"></path>
                        </svg>
                    </button>
                    <a href="{{ route('admin.dashboard') }}" class="flex-1 text-[1.1rem] font-black tracking-[-0.05em] text-black">HYVE {{ $panelLabel }}</a>
                    <span class="text-[0.68rem] font-semibold text-[#7c887d]">Menu</span>
                </div>
            </div>

            <div class="p-5 lg:p-6">
                @if (session('admin_success'))
                    <div class="mb-4 rounded-[1rem] border border-[#d6ebc7] bg-[#f4fbe9] px-4 py-3 text-[0.88rem] text-[#315539]">
                        {{ session('admin_success') }}
                    </div>
                @endif

                @if ($errors->any())
                    <div class="mb-4 rounded-[1rem] border border-red-200 bg-red-50 px-4 py-3 text-[0.84rem] text-red-700">
                        @foreach ($errors->all() as $error)
                            <p>{{ $error }}</p>
                        @endforeach
                    </div>
                @endif

                @yield('content')
            </div>
        </main>
    </div>
</body>
</html>
