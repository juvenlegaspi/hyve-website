@extends('layouts.admin')

@section('content')
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-[1.55rem] font-semibold tracking-[-0.04em] text-[#132320]">Dashboard</h1>
            <p class="mt-1 text-[0.84rem] text-[#a9a293]">{{ now()->format('l, F j, Y') }}</p>
        </div>
{{--  
        <a href="{{ route('bookings.index') }}" class="inline-flex items-center justify-center rounded-[0.85rem] bg-[#44793b] px-4 py-2.5 text-[0.84rem] font-semibold text-white transition hover:bg-[#396733]">
            + New booking
        </a>
        --}}
    </div>

    <section class="mt-5 grid gap-3.5 md:grid-cols-2 xl:grid-cols-4">
        <article class="rounded-[0.95rem] border border-[#e0e4db] bg-white px-4 py-4">
            <p class="text-[0.82rem] text-[#9d978a]">Bookings this month</p>
            <strong class="mt-2 block text-[1.6rem] font-semibold leading-none text-[#132320]">{{ $bookingsThisMonth }}</strong>
            <p class="mt-2 text-[0.78rem] text-[#3f7b3d]">
                {{ $bookingsDelta >= 0 ? '+' : '' }}{{ $bookingsDelta }} from last month
            </p>
        </article>

        <article class="rounded-[0.95rem] border border-[#e0e4db] bg-white px-4 py-4">
            <p class="text-[0.82rem] text-[#9d978a]">Revenue this month</p>
            <strong class="mt-2 block text-[1.6rem] font-semibold leading-none text-[#132320]">&#8369;{{ number_format($revenueThisMonth, 0) }}</strong>
            <p class="mt-2 text-[0.78rem] text-[#3f7b3d]">{{ $verifiedThisMonth }} verified &middot; {{ $pendingThisMonth }} awaiting review</p>
        </article>

        <article class="rounded-[0.95rem] border border-[#e0e4db] bg-white px-4 py-4">
            <p class="text-[0.82rem] text-[#9d978a]">New members this month</p>
            <strong class="mt-2 block text-[1.6rem] font-semibold leading-none text-[#132320]">{{ $newMembersThisMonth }}</strong>
            <p class="mt-2 text-[0.78rem] text-[#3f7b3d]">{{ $membersDelta >= 0 ? '+' : '' }}{{ $membersDelta }} from last month &middot; {{ $memberCount }} active total</p>
        </article>

        <article class="rounded-[0.95rem] border border-[#e0e4db] bg-white px-4 py-4">
            <p class="text-[0.82rem] text-[#9d978a]">Room utilization this month</p>
            <strong class="mt-2 block text-[1.6rem] font-semibold leading-none text-[#132320]">{{ $utilization }}%</strong>
            <p class="mt-2 text-[0.78rem] text-[#3f7b3d]">{{ number_format($bookedHoursThisMonth, 1) }} booked hours across {{ $roomCount }} rooms</p>
        </article>
    </section>

    <section class="mt-4 grid items-start gap-3.5 xl:grid-cols-[1.62fr_1fr]">
        <article class="grid h-[26.8rem] grid-rows-[auto_minmax(0,1fr)] overflow-hidden rounded-[0.95rem] border border-[#e0e4db] bg-white px-5 py-4.5">
            <div class="flex items-center justify-between gap-3">
                <h2 class="text-[1rem] font-semibold tracking-[-0.03em] text-[#132320]">Bookings this month</h2>
                <span class="text-[0.8rem] text-[#aaa398]">Latest 30 this month</span>
            </div>

            <div class="mt-4 min-h-0 rounded-[0.85rem] border border-[#edf1ea] bg-[#fcfdfb] overflow-hidden">
                <div class="h-full overflow-y-auto overflow-x-auto pr-1">
                <table class="min-w-full text-left text-[0.84rem]">
                    <thead class="sticky top-0 z-10 bg-[#fcfdfb] text-[0.68rem] font-bold uppercase tracking-[0.11em] text-[#b3ada1]">
                        <tr>
                            <th class="px-3 pt-3 pb-3">Customer</th>
                            <th class="pt-3 pb-3">Room</th>
                            <th class="pt-3 pb-3">Date</th>
                            <th class="pt-3 pb-3">Time</th>
                            <th class="pt-3 pb-3">Dur</th>
                            <th class="pt-3 pb-3">Amt</th>
                            <th class="pt-3 pb-3 pr-3">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#eef1ea]">
                        @forelse ($recentBookings as $detail)
                            <tr>
                                <td class="px-3 py-2.5 text-[#1a2a26]">
                                    <div class="font-medium">{{ $detail->bookingHeader?->customer_name ?? 'Guest' }}</div>
                                    <div class="mt-1 inline-flex rounded-full bg-[#eef2ff] px-2 py-0.5 text-[0.62rem] font-semibold text-[#4e5ec3]">
                                        {{ $detail->bookingHeader?->booking_type === 'member' ? 'Member' : 'Guest' }}
                                    </div>
                                </td>
                                <td class="py-2.5 text-[#5f6f67]">{{ $detail->hyveRoom?->room_name ?? 'Room' }}</td>
                                <td class="py-2.5 text-[#5f6f67]">{{ optional($detail->booking_date)->format('M j') ?? '--' }}</td>
                                <td class="py-2.5 text-[#5f6f67]">{{ \Illuminate\Support\Carbon::createFromFormat(strlen((string) $detail->start_time) === 5 ? 'H:i' : 'H:i:s', (string) $detail->start_time)->format('g:i A') }}</td>
                                <td class="py-2.5 text-[#5f6f67]">{{ rtrim(rtrim(number_format((float) ($detail->duration_hours ?? 0), 2), '0'), '.') }}hr</td>
                                <td class="py-2.5 text-[#1a2a26]">&#8369;{{ number_format((float) ($detail->subtotal ?? 0), 0) }}</td>
                                <td class="py-2.5 pr-3">
                                    <span class="@if (($detail->status ?? 'pending') === 'confirmed') bg-[#eef8df] text-[#3f6a34] @elseif (($detail->status ?? 'pending') === 'cancelled') bg-[#fde9e5] text-[#b14635] @else bg-[#f8efdd] text-[#9a7832] @endif inline-flex rounded-full px-2.5 py-0.75 text-[0.66rem] font-semibold">
                                        {{ ucfirst((string) ($detail->status ?? 'pending')) }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="py-6 text-center text-[#8f897e]">No bookings yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
                </div>
            </div>
        </article>

        <article class="grid h-[26.8rem] grid-rows-[auto_auto_minmax(0,1fr)] overflow-hidden rounded-[0.95rem] border border-[#e0e4db] bg-white px-5 py-4.5">
            <div class="flex items-center justify-between gap-3">
                <h2 class="text-[1rem] font-semibold tracking-[-0.03em] text-[#132320]">Live rooms status</h2>
                <span class="text-[0.8rem] text-[#aaa398]">As of {{ now()->format('g:i A') }} &middot; refreshes every minute</span>
            </div>

            <div class="mt-3.5 flex flex-wrap gap-4 text-[0.8rem] text-[#68766d]">
                <span class="inline-flex items-center gap-2"><i class="h-3 w-3 rounded-full bg-[#3f7b3d]"></i>Available</span>
                <span class="inline-flex items-center gap-2"><i class="h-3 w-3 rounded-full bg-[#f3a423]"></i>Occupied</span>
                <span class="inline-flex items-center gap-2"><i class="h-3 w-3 rounded-full bg-[#a7a7a7]"></i>Maintenance</span>
            </div>

            <div class="mt-4 min-h-0 grid gap-2.5 overflow-y-auto pr-1 sm:grid-cols-2">
                @foreach ($roomStatus as $room)
                    <div class="@if ($room['status'] === 'available') border-[#abd164] bg-[#f3fae7] @elseif ($room['status'] === 'maintenance') border-[#dedede] bg-[#f6f6f6] @else border-[#e2e3dc] bg-white @endif rounded-[0.9rem] border p-3.5">
                        <p class="text-[0.88rem] font-semibold text-[#163128]">{{ $room['room_name'] }}</p>
                        <p class="mt-1.5 text-[0.82rem] font-semibold @if ($room['status'] === 'available') text-[#3f7b3d] @elseif ($room['status'] === 'maintenance') text-[#777] @else text-[#db8d1e] @endif">
                            {{ $room['status_label'] }}
                        </p>
                        <p class="mt-1 text-[0.74rem] text-[#8f887d]">{{ $room['status_note'] }}</p>
                        @if ($room['until'])
                            <p class="mt-1 text-[0.7rem] text-[#b0aa9f]">{{ $room['until'] }}</p>
                        @endif
                    </div>
                @endforeach
            </div>
        </article>
    </section>

    <script>
        window.setInterval(() => {
            if (!document.hidden) {
                window.location.reload();
            }
        }, 60000);
    </script>
@endsection
