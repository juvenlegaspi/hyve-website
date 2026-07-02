@extends('layouts.admin')

@section('content')
    <style>
        .pricing-rules-page {
            display: grid;
            gap: 1.1rem;
        }

        .pricing-rules-shell {
            display: grid;
            gap: 1rem;
            grid-template-columns: minmax(0, 1.8fr) minmax(18rem, 0.95fr);
        }

        .pricing-card {
            border: 1px solid #e2e8de;
            border-radius: 1.15rem;
            background: rgba(255, 255, 255, 0.98);
            box-shadow: 0 16px 34px rgba(17, 28, 24, 0.06);
        }

        .pricing-card__header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 1rem;
            padding: 1.2rem 1.3rem 1rem;
            border-bottom: 1px solid #edf1ea;
        }

        .pricing-card__title {
            margin: 0;
            color: #132320;
            font-size: 1rem;
            font-weight: 700;
            line-height: 1.2;
            letter-spacing: -0.03em;
        }

        .pricing-card__subtitle {
            margin: 0.26rem 0 0;
            color: #9a9387;
            font-size: 0.75rem;
            line-height: 1.5;
        }

        .pricing-card__meta {
            border-radius: 999px;
            background: #f7f9f4;
            padding: 0.44rem 0.72rem;
            color: #506158;
            font-size: 0.68rem;
            font-weight: 700;
            white-space: nowrap;
        }

        .pricing-rule-list {
            padding: 0.6rem 1.3rem 1.15rem;
            max-height: 36rem;
            overflow-y: auto;
            scrollbar-width: thin;
            scrollbar-color: #b9c8b3 transparent;
        }

        .pricing-rule-list::-webkit-scrollbar {
            width: 0.45rem;
        }

        .pricing-rule-list::-webkit-scrollbar-track {
            background: transparent;
        }

        .pricing-rule-list::-webkit-scrollbar-thumb {
            border-radius: 999px;
            background: #c8d3c1;
        }

        .pricing-rule-list::-webkit-scrollbar-thumb:hover {
            background: #afbeaa;
        }

        .pricing-rule-grid {
            display: grid;
            grid-template-columns: minmax(0, 1.15fr) minmax(0, 0.7fr) minmax(0, 1.4fr) minmax(0, 0.8fr) minmax(0, 0.75fr) minmax(6.4rem, 0.8fr);
            gap: 0 1rem;
            align-items: center;
        }

        .pricing-rule-head {
            padding: 0 0.05rem 0.75rem;
            color: #b2aca0;
            font-size: 0.69rem;
            font-weight: 800;
            letter-spacing: 0.12em;
            text-transform: uppercase;
        }

        .pricing-rule-row {
            padding: 1rem 0.05rem;
            border-top: 1px solid #eef1ea;
        }

        .pricing-rule-name {
            margin: 0;
            color: #173029;
            font-size: 0.88rem;
            font-weight: 700;
            line-height: 1.35;
        }

        .pricing-rule-slug {
            margin-top: 0.26rem;
            color: #8f887d;
            font-size: 0.72rem;
            line-height: 1.45;
        }

        .pricing-rule-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 999px;
            padding: 0.35rem 0.66rem;
            font-size: 0.67rem;
            font-weight: 700;
            line-height: 1;
            white-space: nowrap;
        }

        .pricing-rule-badge--base {
            background: #eef8df;
            color: #3d6c34;
        }

        .pricing-rule-badge--night {
            background: #eef2ff;
            color: #4864c7;
        }

        .pricing-rule-badge--mixed {
            background: #fff3e2;
            color: #b6701c;
        }

        .pricing-rule-rooms {
            color: #5f6d65;
            font-size: 0.75rem;
            line-height: 1.65;
        }

        .pricing-rule-rate {
            color: #173029;
            font-size: 0.79rem;
            font-weight: 700;
            line-height: 1.5;
        }

        .pricing-rule-rate span {
            display: block;
            color: #8c857a;
            font-size: 0.68rem;
            font-weight: 600;
        }

        .pricing-rule-priority {
            color: #5d6a63;
            font-size: 0.76rem;
            font-weight: 700;
        }

        .pricing-rule-actions {
            display: flex;
            justify-content: flex-end;
        }

        .pricing-rule-edit {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.4rem;
            min-height: 2.3rem;
            border: 1px solid #d9dfd6;
            border-radius: 999px;
            background: linear-gradient(180deg, #ffffff 0%, #f7faf4 100%);
            padding: 0.55rem 0.95rem;
            color: #315438;
            font-size: 0.73rem;
            font-weight: 700;
            box-shadow: 0 10px 22px rgba(41, 70, 48, 0.08);
            transition: transform 160ms ease, border-color 160ms ease, box-shadow 160ms ease;
        }

        .pricing-rule-edit:hover {
            transform: translateY(-1px);
            border-color: #b9cbaf;
            box-shadow: 0 12px 24px rgba(41, 70, 48, 0.12);
        }

        .pricing-side {
            display: grid;
            gap: 1rem;
            align-content: start;
        }

        .pricing-info-stack {
            display: grid;
            gap: 0.8rem;
            padding: 1.15rem 1.2rem 1.2rem;
        }

        .pricing-info-box {
            border: 1px solid #e7ece4;
            border-radius: 0.95rem;
            background: #fbfcf8;
            padding: 0.95rem 1rem;
        }

        .pricing-info-label {
            color: #b3aca0;
            font-size: 0.68rem;
            font-weight: 800;
            letter-spacing: 0.14em;
            text-transform: uppercase;
        }

        .pricing-info-value {
            margin-top: 0.38rem;
            color: #173029;
            font-size: 0.84rem;
            font-weight: 700;
            line-height: 1.45;
        }

        .pricing-info-copy {
            margin-top: 0.32rem;
            color: #878175;
            font-size: 0.73rem;
            line-height: 1.65;
        }

        .pricing-peaks {
            display: grid;
            gap: 1rem;
            padding: 1.15rem 1.2rem 1.2rem;
        }

        .pricing-peak-grid {
            display: grid;
            grid-template-columns: 1.1fr minmax(0, 0.9fr);
            gap: 1rem;
        }

        .pricing-window {
            border: 1px solid #e6ebe1;
            border-radius: 1rem;
            background: #fbfcf9;
            padding: 0.95rem 1rem;
        }

        .pricing-window__row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.8rem;
            padding: 0.6rem 0;
            border-bottom: 1px solid #edf1eb;
        }

        .pricing-window__row:last-child {
            padding-bottom: 0;
            border-bottom: 0;
        }

        .pricing-window__name {
            color: #163028;
            font-size: 0.79rem;
            font-weight: 700;
        }

        .pricing-window__desc {
            color: #918a7f;
            font-size: 0.71rem;
            line-height: 1.5;
        }

        .pricing-window__pill {
            flex-shrink: 0;
            border-radius: 999px;
            background: #eef5de;
            padding: 0.4rem 0.7rem;
            color: #3d6c34;
            font-size: 0.68rem;
            font-weight: 700;
        }

        .pricing-status-box {
            border: 1px solid #e6ebe1;
            border-radius: 1rem;
            background: linear-gradient(180deg, #fbfcf8 0%, #f6faf1 100%);
            padding: 1rem;
        }

        .pricing-status-box__title {
            color: #173029;
            font-size: 0.82rem;
            font-weight: 700;
        }

        .pricing-status-box__text {
            margin-top: 0.42rem;
            color: #8a8477;
            font-size: 0.73rem;
            line-height: 1.65;
        }

        .pricing-status-box__chips {
            display: flex;
            flex-wrap: wrap;
            gap: 0.45rem;
            margin-top: 0.8rem;
        }

        .pricing-status-chip {
            border-radius: 999px;
            background: #ffffff;
            border: 1px solid #dfe7d9;
            padding: 0.34rem 0.62rem;
            color: #4a5f54;
            font-size: 0.68rem;
            font-weight: 700;
        }

        .pricing-rules-modal {
            position: fixed;
            inset: 0;
            z-index: 140;
            display: flex;
            align-items: flex-start;
            justify-content: center;
            overflow-y: auto;
            padding: 1.1rem;
        }

        .pricing-rules-modal.hidden {
            display: none !important;
        }

        .pricing-rules-modal__backdrop {
            position: absolute;
            inset: 0;
            background: rgba(17, 25, 22, 0.38);
            backdrop-filter: blur(10px);
        }

        .pricing-rules-modal__card {
            position: relative;
            z-index: 1;
            width: min(42rem, 100%);
            max-height: calc(100vh - 2.2rem);
            overflow-y: auto;
            border: 1px solid rgba(19, 35, 32, 0.08);
            border-radius: 1.6rem;
            background: rgba(255, 255, 255, 0.98);
            box-shadow: 0 28px 70px rgba(17, 28, 24, 0.18);
            padding: 1.55rem 1.6rem 1.35rem;
        }

        .pricing-rules-modal__top {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 1rem;
            margin-bottom: 1.15rem;
        }

        .pricing-rules-modal__title {
            margin: 0;
            color: #202823;
            font-size: 1rem;
            font-weight: 700;
            line-height: 1.2;
        }

        .pricing-rules-modal__subtitle {
            margin: 0.22rem 0 0;
            color: #7a8178;
            font-size: 0.76rem;
            line-height: 1.45;
        }

        .pricing-rules-modal__close {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 2rem;
            height: 2rem;
            border: 0;
            border-radius: 999px;
            background: #f3f4ef;
            color: #657065;
            font-size: 1.2rem;
            line-height: 1;
            cursor: pointer;
            transition: background 160ms ease, color 160ms ease;
        }

        .pricing-rules-modal__close:hover {
            background: #ecefe7;
            color: #24332c;
        }

        .pricing-rules-modal__grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0.85rem 0.75rem;
        }

        .pricing-rules-modal__field {
            display: grid;
            gap: 0.4rem;
        }

        .pricing-rules-modal__field--full {
            grid-column: 1 / -1;
        }

        .pricing-rules-modal__label {
            color: #b1aea7;
            font-size: 0.71rem;
            font-weight: 800;
            letter-spacing: 0.05em;
            text-transform: uppercase;
        }

        .pricing-rules-modal__control {
            width: 100%;
            min-height: 2.85rem;
            border: 1px solid #dde3da;
            border-radius: 0.92rem;
            background: #fbfbf8;
            color: #1f2822;
            font-size: 0.81rem;
            padding: 0.78rem 0.92rem;
            outline: none;
            transition: border-color 160ms ease, background 160ms ease, box-shadow 160ms ease;
        }

        .pricing-rules-modal__control:focus {
            border-color: rgba(68, 121, 59, 0.4);
            background: #fff;
            box-shadow: 0 0 0 3px rgba(68, 121, 59, 0.08);
        }

        .pricing-rules-modal__meta {
            display: flex;
            flex-wrap: wrap;
            gap: 0.45rem;
            min-height: 2.85rem;
            max-height: 8.25rem;
            overflow-y: auto;
            align-items: flex-start;
            padding: 0.68rem 0.8rem;
            border: 1px solid #e3e8df;
            border-radius: 0.92rem;
            background: #f9fbf7;
            scrollbar-width: thin;
            scrollbar-color: #c5d1bd transparent;
        }

        .pricing-rules-modal__meta::-webkit-scrollbar {
            width: 0.42rem;
        }

        .pricing-rules-modal__meta::-webkit-scrollbar-track {
            background: transparent;
        }

        .pricing-rules-modal__meta::-webkit-scrollbar-thumb {
            border-radius: 999px;
            background: #c5d1bd;
        }

        .pricing-rules-modal__meta::-webkit-scrollbar-thumb:hover {
            background: #adbeaa;
        }

        .pricing-rules-modal__chip {
            display: inline-flex;
            align-items: center;
            border-radius: 999px;
            background: #eef5de;
            padding: 0.34rem 0.64rem;
            color: #3e6c35;
            font-size: 0.7rem;
            font-weight: 700;
        }

        .pricing-rules-modal__hint {
            color: #889186;
            font-size: 0.72rem;
            line-height: 1.45;
        }

        .pricing-rules-modal__toggle {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.9rem;
            min-height: 2.85rem;
            padding: 0.78rem 0.92rem;
            border: 1px solid #dde3da;
            border-radius: 0.92rem;
            background: #fbfbf8;
        }

        .pricing-rules-modal__toggle input {
            width: 1rem;
            height: 1rem;
            accent-color: #44793b;
        }

        .pricing-rules-modal__footer {
            display: flex;
            align-items: center;
            gap: 0.65rem;
            margin-top: 1.2rem;
            padding-top: 1rem;
            border-top: 1px solid rgba(17, 52, 44, 0.08);
        }

        .pricing-rules-modal__button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 2.95rem;
            padding: 0.82rem 1.2rem;
            border-radius: 0.95rem;
            font-size: 0.8rem;
            font-weight: 700;
            transition: transform 160ms ease, background 160ms ease, border-color 160ms ease;
        }

        .pricing-rules-modal__button:hover {
            transform: translateY(-1px);
        }

        .pricing-rules-modal__button--ghost {
            border: 1px solid #dde4da;
            background: #fff;
            color: #55645c;
        }

        .pricing-rules-modal__button--primary {
            margin-left: auto;
            min-width: 10.5rem;
            border: 1px solid transparent;
            background: #44793b;
            color: #fff;
        }

        .pricing-rules-modal__button--primary:hover {
            background: #3a6933;
        }

        @media (max-width: 1280px) {
            .pricing-rules-shell,
            .pricing-peak-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 920px) {
            .pricing-rule-grid {
                grid-template-columns: minmax(0, 1fr);
                gap: 0.45rem;
            }

            .pricing-rule-head {
                display: none;
            }

            .pricing-rule-row {
                display: grid;
                gap: 0.55rem;
            }

            .pricing-rule-actions {
                justify-content: flex-start;
                padding-top: 0.2rem;
            }

            .pricing-rules-modal__grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 760px) {
            .pricing-rules-modal {
                padding: 0.7rem;
            }

            .pricing-rules-modal__card {
                max-height: calc(100vh - 1.4rem);
                padding: 1rem 1rem 0.95rem;
                border-radius: 1.15rem;
            }

            .pricing-rules-modal__field--full {
                grid-column: auto;
            }

            .pricing-rules-modal__footer {
                flex-wrap: wrap;
            }

            .pricing-rules-modal__button,
            .pricing-rules-modal__button--primary {
                width: 100%;
                min-width: 0;
                margin-left: 0;
            }
        }
    </style>

    @php
        $validationRate = $rates->firstWhere('id', (int) old('rate_id'));

        $buildRateLabel = static function ($rate): string {
            return 'Php '.number_format((float) $rate->day_minimum_rate, 2).' base';
        };

        $buildPriorityLabel = static function ($index): string {
            return match ($index) {
                1 => '1 - highest',
                2 => '2',
                3 => '3',
                default => $index.' - live',
            };
        };
    @endphp

    <div class="pricing-rules-page">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="text-[1.45rem] font-semibold tracking-[-0.04em] text-[#132320]">Pricing Rules</h1>
                <p class="mt-1 text-[0.78rem] text-[#a9a293]">Live rates used by room booking, holiday pricing, and quote computation</p>
            </div>
        </div>

        <section class="pricing-rules-shell">
            <article class="pricing-card">
                <div class="pricing-card__header">
                    <div>
                        <h2 class="pricing-card__title">Active pricing rules</h2>
                        <p class="pricing-card__subtitle">Priority flow: active room card -> holiday behavior -> night window -> live booking checkout.</p>
                    </div>

                    <span class="pricing-card__meta">{{ $rates->where('is_active', true)->count() }} active of {{ $rates->count() }}</span>
                </div>

                <div class="pricing-rule-list">
                    <div class="pricing-rule-grid pricing-rule-head">
                        <div>Rule</div>
                        <div>Type</div>
                        <div>Applies to</div>
                        <div>Rate</div>
                        <div>Priority</div>
                        <div class="text-right">Action</div>
                    </div>

                    @foreach ($rates as $index => $rate)
                        @php
                            $mappedRooms = $roomMappings[$rate->space_slug] ?? [];
                            $typeLabel = str_contains($rate->space_slug, 'common')
                                ? 'Shared'
                                : (str_contains($rate->space_slug, 'zeal') ? 'Meeting' : 'Private');
                            $badgeClass = str_contains($rate->space_slug, 'common')
                                ? 'pricing-rule-badge--mixed'
                                : (str_contains($rate->space_slug, 'zeal') ? 'pricing-rule-badge--night' : 'pricing-rule-badge--base');
                            $roomDisplay = str_contains($rate->space_slug, 'common')
                                ? 'Common area tables'
                                : ($mappedRooms !== [] ? implode(', ', $mappedRooms) : 'No mapped rooms yet');
                        @endphp

                        <div class="pricing-rule-grid pricing-rule-row">
                            <div>
                                <p class="pricing-rule-name">{{ $rate->title }}</p>
                                <p class="pricing-rule-slug">{{ $rate->space_slug }}</p>
                            </div>

                            <div>
                                <span class="pricing-rule-badge {{ $badgeClass }}">{{ $typeLabel }}</span>
                            </div>

                            <div class="pricing-rule-rooms">
                                {{ $roomDisplay }}
                            </div>

                            <div class="pricing-rule-rate">
                                Php {{ number_format((float) $rate->day_minimum_rate, 2) }}
                                <span>{{ $rate->minimum_hours }} hr min · +Php {{ number_format((float) $rate->day_succeeding_hour_rate, 2) }}/hr</span>
                            </div>

                            <div class="pricing-rule-priority">{{ $buildPriorityLabel($index + 1) }}</div>

                            <div class="pricing-rule-actions">
                                <button
                                    type="button"
                                    data-pricing-rule-open
                                    data-action="{{ route('admin.pricing-rules.update', $rate) }}"
                                    data-rate-id="{{ $rate->id }}"
                                    data-title="{{ $rate->title }}"
                                    data-space-slug="{{ $rate->space_slug }}"
                                    data-minimum-hours="{{ $rate->minimum_hours }}"
                                    data-day-minimum-rate="{{ number_format((float) $rate->day_minimum_rate, 2, '.', '') }}"
                                    data-day-succeeding-hour-rate="{{ number_format((float) $rate->day_succeeding_hour_rate, 2, '.', '') }}"
                                    data-night-minimum-rate="{{ number_format((float) $rate->night_minimum_rate, 2, '.', '') }}"
                                    data-night-succeeding-hour-rate="{{ number_format((float) $rate->night_succeeding_hour_rate, 2, '.', '') }}"
                                    data-is-active="{{ $rate->is_active ? '1' : '0' }}"
                                    data-mapped-rooms="{{ implode(' | ', $mappedRooms) }}"
                                    class="pricing-rule-edit"
                                >
                                    <span>Edit</span>
                                    <svg viewBox="0 0 16 16" class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="1.2">
                                        <path d="M3.25 12.75h2.1l6.05-6.05-2.1-2.1-6.05 6.05v2.1Z"></path>
                                        <path d="m8.95 4.95 2.1 2.1M9.65 3.2l2.1 2.1"></path>
                                    </svg>
                                </button>
                            </div>
                        </div>
                    @endforeach
                </div>
            </article>

            <aside class="pricing-side">
                <article class="pricing-card">
                    <div class="pricing-card__header">
                        <div>
                            <h2 class="pricing-card__title">Booking pricing behavior</h2>
                            <p class="pricing-card__subtitle">These notes mirror the live booking logic already connected in the customer flow.</p>
                        </div>
                    </div>

                    <div class="pricing-info-stack">
                        <div class="pricing-info-box">
                            <div class="pricing-info-label">Day use window</div>
                            <div class="pricing-info-value">6:00 AM to 5:59 PM</div>
                            <div class="pricing-info-copy">Start times inside this window use the day base rate and day succeeding hourly rate of the selected room card.</div>
                        </div>

                        <div class="pricing-info-box">
                            <div class="pricing-info-label">Night / peak window</div>
                            <div class="pricing-info-value">6:00 PM to 5:59 AM</div>
                            <div class="pricing-info-copy">Night-start bookings automatically switch to the night base and night succeeding hourly rate of the same room card.</div>
                        </div>

                        <div class="pricing-info-box">
                            <div class="pricing-info-label">Holiday behavior</div>
                            <div class="pricing-info-value">Calendar holiday -> night rate</div>
                            <div class="pricing-info-copy">If the booking date is marked as a PH holiday in Calendar & Events, the room automatically follows peak/night pricing.</div>
                        </div>

                        <div class="pricing-info-box">
                            <div class="pricing-info-label">Downpayment rule</div>
                            <div class="pricing-info-value">Automatic at checkout</div>
                            <div class="pricing-info-copy">Below Php 1,000: 50% downpayment. Above Php 1,000: minimum Php 500 downpayment before booking verification.</div>
                        </div>
                    </div>
                </article>
            </aside>
        </section>

    </div>

    <div id="pricing-rule-modal" data-pricing-rule-modal class="pricing-rules-modal hidden">
        <button type="button" class="pricing-rules-modal__backdrop" data-pricing-rule-close aria-label="Close pricing rule modal"></button>

        <div class="pricing-rules-modal__card">
            <div class="pricing-rules-modal__top">
                <div>
                    <h2 class="pricing-rules-modal__title">Edit pricing rule</h2>
                    <p class="pricing-rules-modal__subtitle">Any save here updates the live rate card used during customer booking and quoting.</p>
                </div>

                <button type="button" class="pricing-rules-modal__close" data-pricing-rule-close aria-label="Close pricing rule modal">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>

            <form id="pricing-rule-form" method="POST" action="{{ $validationRate ? route('admin.pricing-rules.update', $validationRate) : '#' }}">
                @csrf
                @method('PATCH')
                <input type="hidden" name="rate_id" id="pricing-rule-rate-id" value="{{ old('rate_id') }}">

                <div class="pricing-rules-modal__grid">
                    <div class="pricing-rules-modal__field pricing-rules-modal__field--full">
                        <label for="pricing-rule-title" class="pricing-rules-modal__label">Rule name</label>
                        <input id="pricing-rule-title" type="text" name="title" class="pricing-rules-modal__control" value="{{ old('title') }}">
                    </div>

                    <div class="pricing-rules-modal__field pricing-rules-modal__field--full">
                        <label class="pricing-rules-modal__label">Mapped rooms</label>
                        <div id="pricing-rule-mapped-rooms" class="pricing-rules-modal__meta">
                            <span class="pricing-rules-modal__hint">Select a pricing rule to view its mapped rooms.</span>
                        </div>
                    </div>

                    <div class="pricing-rules-modal__field">
                        <label for="pricing-rule-minimum-hours" class="pricing-rules-modal__label">Minimum hours</label>
                        <input id="pricing-rule-minimum-hours" type="number" min="1" max="24" name="minimum_hours" class="pricing-rules-modal__control" value="{{ old('minimum_hours') }}">
                    </div>

                    <div class="pricing-rules-modal__field">
                        <label class="pricing-rules-modal__label">Rate key</label>
                        <div class="pricing-rules-modal__meta">
                            <span id="pricing-rule-space-slug" class="pricing-rules-modal__chip">{{ $validationRate?->space_slug ?? 'Space slug' }}</span>
                        </div>
                    </div>

                    <div class="pricing-rules-modal__field">
                        <label for="pricing-rule-day-minimum-rate" class="pricing-rules-modal__label">Day base rate</label>
                        <input id="pricing-rule-day-minimum-rate" type="number" step="0.01" min="0" name="day_minimum_rate" class="pricing-rules-modal__control" value="{{ old('day_minimum_rate') }}">
                    </div>

                    <div class="pricing-rules-modal__field">
                        <label for="pricing-rule-day-succeeding-hour-rate" class="pricing-rules-modal__label">Day + / hour</label>
                        <input id="pricing-rule-day-succeeding-hour-rate" type="number" step="0.01" min="0" name="day_succeeding_hour_rate" class="pricing-rules-modal__control" value="{{ old('day_succeeding_hour_rate') }}">
                    </div>

                    <div class="pricing-rules-modal__field">
                        <label for="pricing-rule-night-minimum-rate" class="pricing-rules-modal__label">Night base rate</label>
                        <input id="pricing-rule-night-minimum-rate" type="number" step="0.01" min="0" name="night_minimum_rate" class="pricing-rules-modal__control" value="{{ old('night_minimum_rate') }}">
                    </div>

                    <div class="pricing-rules-modal__field">
                        <label for="pricing-rule-night-succeeding-hour-rate" class="pricing-rules-modal__label">Night + / hour</label>
                        <input id="pricing-rule-night-succeeding-hour-rate" type="number" step="0.01" min="0" name="night_succeeding_hour_rate" class="pricing-rules-modal__control" value="{{ old('night_succeeding_hour_rate') }}">
                    </div>

                    <div class="pricing-rules-modal__field pricing-rules-modal__field--full">
                        <label class="pricing-rules-modal__label">Status</label>
                        <label class="pricing-rules-modal__toggle">
                            <span class="text-[0.79rem] font-medium text-[#22342c]">Allow this rate card in live booking</span>
                            <input id="pricing-rule-is-active" type="checkbox" name="is_active" value="1" @checked(old('is_active'))>
                        </label>
                        <p class="pricing-rules-modal__hint">Inactive pricing cards stop customer bookings from using rooms mapped to this rate.</p>
                    </div>
                </div>

                <div class="pricing-rules-modal__footer">
                    <button type="button" class="pricing-rules-modal__button pricing-rules-modal__button--ghost" data-pricing-rule-close>
                        Cancel
                    </button>
                    <button type="submit" class="pricing-rules-modal__button pricing-rules-modal__button--primary">
                        Save pricing rule
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        (() => {
            const modal = document.querySelector('[data-pricing-rule-modal]');
            const form = document.getElementById('pricing-rule-form');

            if (!modal || !form) {
                return;
            }

            const fields = {
                rateId: document.getElementById('pricing-rule-rate-id'),
                title: document.getElementById('pricing-rule-title'),
                minimumHours: document.getElementById('pricing-rule-minimum-hours'),
                spaceSlug: document.getElementById('pricing-rule-space-slug'),
                dayMinimumRate: document.getElementById('pricing-rule-day-minimum-rate'),
                daySucceedingHourRate: document.getElementById('pricing-rule-day-succeeding-hour-rate'),
                nightMinimumRate: document.getElementById('pricing-rule-night-minimum-rate'),
                nightSucceedingHourRate: document.getElementById('pricing-rule-night-succeeding-hour-rate'),
                isActive: document.getElementById('pricing-rule-is-active'),
                mappedRooms: document.getElementById('pricing-rule-mapped-rooms'),
            };

            const openModal = (button) => {
                fields.rateId.value = button.dataset.rateId || '';
                fields.title.value = button.dataset.title || '';
                fields.minimumHours.value = button.dataset.minimumHours || '';
                fields.spaceSlug.textContent = button.dataset.spaceSlug || 'Space slug';
                fields.dayMinimumRate.value = button.dataset.dayMinimumRate || '';
                fields.daySucceedingHourRate.value = button.dataset.daySucceedingHourRate || '';
                fields.nightMinimumRate.value = button.dataset.nightMinimumRate || '';
                fields.nightSucceedingHourRate.value = button.dataset.nightSucceedingHourRate || '';
                fields.isActive.checked = (button.dataset.isActive || '0') === '1';

                const roomList = (button.dataset.mappedRooms || '')
                    .split('|')
                    .map((item) => item.trim())
                    .filter(Boolean);

                fields.mappedRooms.innerHTML = roomList.length
                    ? roomList.map((room) => `<span class="pricing-rules-modal__chip">${room}</span>`).join('')
                    : '<span class="pricing-rules-modal__hint">No room is currently mapped to this pricing card.</span>';

                form.action = button.dataset.action || '#';
                modal.classList.remove('hidden');
                document.body.style.overflow = 'hidden';
            };

            const closeModal = () => {
                modal.classList.add('hidden');
                document.body.style.overflow = '';
            };

            document.querySelectorAll('[data-pricing-rule-open]').forEach((button) => {
                button.addEventListener('click', () => openModal(button));
            });

            document.querySelectorAll('[data-pricing-rule-close]').forEach((button) => {
                button.addEventListener('click', closeModal);
            });

            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape' && !modal.classList.contains('hidden')) {
                    closeModal();
                }
            });

            @if ($validationRate)
                const validationButton = document.querySelector('[data-pricing-rule-open][data-rate-id="{{ $validationRate->id }}"]');
                if (validationButton) {
                    openModal(validationButton);
                    fields.rateId.value = @json(old('rate_id'));
                    fields.title.value = @json(old('title'));
                    fields.minimumHours.value = @json(old('minimum_hours'));
                    fields.dayMinimumRate.value = @json(old('day_minimum_rate'));
                    fields.daySucceedingHourRate.value = @json(old('day_succeeding_hour_rate'));
                    fields.nightMinimumRate.value = @json(old('night_minimum_rate'));
                    fields.nightSucceedingHourRate.value = @json(old('night_succeeding_hour_rate'));
                    fields.isActive.checked = @json((bool) old('is_active'));
                }
            @endif
        })();
    </script>
@endsection
