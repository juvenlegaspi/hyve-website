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
            'Users' => 'users',
            'Reports' => 'chart',
            'Admin Roles' => 'shield',
            'Settings' => 'gear',
        ];
    @endphp

    <div class="flex min-h-screen">
        <aside class="max-lg:hidden sticky top-0 h-screen w-[14.3rem] shrink-0 border-r border-[#dfe5db] bg-white lg:flex lg:flex-col">
            <div class="border-b border-[#edf1ea] px-4 py-6">
                <a href="{{ route('admin.dashboard') }}" class="flex items-baseline gap-2">
                    <span class="text-[1.18rem] font-black tracking-[-0.04em] text-black">HYVE</span>
                    <span class="text-[0.78rem] font-medium text-black">Admin</span>
                </a>
            </div>

            <nav class="flex-1 overflow-y-auto px-0 py-2">
                @foreach (($sidebarSections ?? []) as $section)
                    @if (! empty($section['title']))
                        <p class="px-4 pb-2 pt-4 text-[0.7rem] font-bold uppercase tracking-[0.18em] text-[#d0d3cb]">
                            {{ $section['title'] }}
                        </p>
                    @endif

                    <div class="grid gap-1">
                        @foreach ($section['items'] as $item)
                            @php($icon = $sidebarIcons[$item['label']] ?? 'grid')
                            <a
                                href="{{ route($item['route']) }}"
                                class="@if (request()->routeIs($item['route'])) border-r-[3px] border-[#5e8b43] bg-[#edf5df] text-[#224133] @else text-[#69736a] hover:bg-[#fafbf7] @endif flex items-center gap-3 px-4 py-2.5 text-[0.8rem] font-medium transition"
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
                                {{ $item['label'] }}
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
                        <p class="truncate text-[0.68rem] text-[#8c9682]">{{ $adminUser->email ?? str_replace('_', ' ', (string) ($adminUser->role ?? 'admin')) }}</p>
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

        <main class="min-w-0 flex-1">
            <div class="border-b border-[#e6eadf] bg-white px-4 py-4 lg:hidden">
                <div class="flex items-center justify-between gap-3">
                    <a href="{{ route('admin.dashboard') }}" class="text-[1.2rem] font-black tracking-[-0.05em] text-black">HYVE Admin</a>
                    <form action="{{ route('logout') }}" method="POST">
                        @csrf
                        <button type="submit" class="rounded-full border border-[#dbe5d1] px-3 py-2 text-[0.74rem] font-semibold text-[#48624f]">
                            Log out
                        </button>
                    </form>
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
