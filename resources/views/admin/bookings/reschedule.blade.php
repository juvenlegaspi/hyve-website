@extends('layouts.admin')

@section('content')
    @php
        $currentStartDate = old('booking_date', optional($bookingDetail->booking_date)->toDateString());
        $currentEndDate = old('booking_end_date', optional($bookingDetail->booking_end_date ?: $bookingDetail->booking_date)->toDateString());
        $currentStartTime = old('start_time', substr((string) $bookingDetail->start_time, 0, 5));
        $currentEndTime = old('end_time', substr((string) $bookingDetail->end_time, 0, 5));
        $currentUseType = old('long_stay_use_type', in_array((string) $bookingDetail->charge_period, ['day', 'night'], true) ? $bookingDetail->charge_period : '');
        $currentRoom = $bookingDetail->hyveRoom?->isSharedTable() ? 'Common Area' : ($bookingDetail->hyveRoom?->room_name ?? $bookingDetail->space?->name ?? 'Room');
        $currentDate = $isLongStay
            ? optional($bookingDetail->booking_date)->format('M j, Y').' - '.optional($bookingDetail->booking_end_date ?: $bookingDetail->booking_date)->format('M j, Y')
            : optional($bookingDetail->booking_date)->format('M j, Y');
        $currentTime = $isLongStay
            ? ucfirst((string) $bookingDetail->charge_period).' stay'
            : \Illuminate\Support\Carbon::parse((string) $bookingDetail->start_time)->format('g:i A').' - '.\Illuminate\Support\Carbon::parse((string) $bookingDetail->end_time)->format('g:i A');
    @endphp

    <div class="mx-auto max-w-[1180px] space-y-5">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <p class="text-[0.68rem] font-bold uppercase tracking-[0.22em] text-[#b39a5a]">Booking management</p>
                <h1 class="mt-2 text-[1.75rem] font-semibold tracking-[-0.05em] text-[#132320]">Reschedule booking</h1>
                <p class="mt-1 max-w-2xl text-[0.84rem] leading-6 text-[#7a7d75]">Move this reservation while keeping its reference, payment records, and customer history intact.</p>
            </div>
            <a href="{{ route('admin.bookings.index') }}" class="rounded-[0.9rem] border border-[#dfe5db] bg-white px-4 py-2.5 text-[0.8rem] font-semibold text-[#36534c]">Back to bookings</a>
        </div>

        @if ($errors->any())
            <div class="rounded-[1rem] border border-[#efc7bf] bg-[#fff5f3] px-4 py-3 text-[0.82rem] text-[#9f3d2f]">
                <strong>Please review the reschedule details.</strong>
                <ul class="mt-2 list-disc space-y-1 pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form
            method="POST"
            action="{{ route('admin.booking-details.reschedule.update', $bookingDetail) }}"
            class="grid gap-5 xl:grid-cols-[1.2fr_0.8fr]"
            data-admin-reschedule-form
            data-slots-url="{{ $slotsUrl }}"
            data-preview-url="{{ $previewUrl }}"
            data-is-long-stay="{{ $isLongStay ? '1' : '0' }}"
        >
            @csrf
            @method('PATCH')

            <section class="rounded-[1.35rem] border border-[#dfe5db] bg-white p-5 shadow-[0_18px_55px_rgba(26,47,38,0.06)]">
                <div class="border-b border-[#edf1ea] pb-4">
                    <p class="text-[0.68rem] font-bold uppercase tracking-[0.18em] text-[#b39a5a]">New schedule</p>
                    <h2 class="mt-1 text-[1.1rem] font-semibold text-[#173029]">Select the replacement slot</h2>
                    <p class="mt-1 text-[0.78rem] leading-5 text-[#85877f]">Availability and updated pricing will be checked automatically.</p>
                </div>

                <div class="mt-5 grid gap-4">
                    <label class="grid gap-1.5 text-[0.76rem] font-semibold text-[#52635d]">
                        Room
                        <select name="hyve_room_id" class="min-h-12 rounded-xl border border-[#dfe5db] bg-[#fbfcf8] px-3 text-[0.84rem] outline-none focus:border-[#6f9659]" required>
                            @foreach ($displayRooms as $room)
                                <option value="{{ $room->id }}" @selected((string) old('hyve_room_id', $selectedRoomId) === (string) $room->id)>
                                    {{ $room->isSharedTable() ? 'Common Area' : $room->room_name }} — {{ $room->mappedSpaceLabel() }}
                                </option>
                            @endforeach
                        </select>
                    </label>

                    @if ($isLongStay)
                        <div class="grid gap-4 md:grid-cols-2">
                            <label class="grid gap-1.5 text-[0.76rem] font-semibold text-[#52635d]">
                                Start date
                                <input type="date" name="booking_date" min="{{ now()->toDateString() }}" value="{{ $currentStartDate }}" class="min-h-12 rounded-xl border border-[#dfe5db] bg-[#fbfcf8] px-3 text-[0.84rem] outline-none focus:border-[#6f9659]" required>
                            </label>
                            <label class="grid gap-1.5 text-[0.76rem] font-semibold text-[#52635d]">
                                End date
                                <input type="date" name="booking_end_date" min="{{ $currentStartDate }}" value="{{ $currentEndDate }}" class="min-h-12 rounded-xl border border-[#dfe5db] bg-[#fbfcf8] px-3 text-[0.84rem] outline-none focus:border-[#6f9659]" required>
                            </label>
                        </div>
                        <label class="grid gap-1.5 text-[0.76rem] font-semibold text-[#52635d]">
                            Use period
                            <select name="long_stay_use_type" class="min-h-12 rounded-xl border border-[#dfe5db] bg-[#fbfcf8] px-3 text-[0.84rem] outline-none focus:border-[#6f9659]">
                                <option value="" @selected($currentUseType === '')>Automatic / monthly rate</option>
                                <option value="day" @selected($currentUseType === 'day')>Day use</option>
                                <option value="night" @selected($currentUseType === 'night')>Night use</option>
                            </select>
                        </label>
                    @else
                        <label class="grid gap-1.5 text-[0.76rem] font-semibold text-[#52635d]">
                            Booking date
                            <input type="date" name="booking_date" min="{{ now()->toDateString() }}" value="{{ $currentStartDate }}" class="min-h-12 rounded-xl border border-[#dfe5db] bg-[#fbfcf8] px-3 text-[0.84rem] outline-none focus:border-[#6f9659]" required>
                        </label>
                        <input type="hidden" name="start_time" value="{{ $currentStartTime }}" data-selected-start required>
                        <input type="hidden" name="end_time" value="{{ $currentEndTime }}" data-selected-end required>

                        <div class="grid gap-3">
                            <div>
                                <p class="text-[0.76rem] font-semibold text-[#52635d]">1. Choose start time</p>
                                <p class="mt-1 text-[0.72rem] text-[#92958d]">Only future slots with at least a two-hour opening can be selected.</p>
                            </div>
                            <div class="grid max-h-[22rem] grid-cols-2 gap-2 overflow-y-auto pr-1 sm:grid-cols-3 md:grid-cols-4" data-start-slots>
                                <div class="col-span-full rounded-xl border border-dashed border-[#dfe5db] px-4 py-5 text-center text-[0.76rem] text-[#92958d]">Loading available start times…</div>
                            </div>
                        </div>

                        <div class="grid gap-3" data-end-step>
                            <div>
                                <p class="text-[0.76rem] font-semibold text-[#52635d]">2. Choose end time</p>
                                <p class="mt-1 text-[0.72rem] text-[#92958d]" data-end-help>Select a start time first.</p>
                            </div>
                            <div class="grid max-h-[22rem] grid-cols-2 gap-2 overflow-y-auto pr-1 sm:grid-cols-3 md:grid-cols-4" data-end-slots>
                                <div class="col-span-full rounded-xl border border-dashed border-[#dfe5db] px-4 py-5 text-center text-[0.76rem] text-[#92958d]">Waiting for a start time…</div>
                            </div>
                        </div>
                    @endif
                </div>

                <div class="mt-5 rounded-xl border border-[#e4e8df] bg-[#f9faf6] px-4 py-3 text-[0.78rem] text-[#68736b]" data-reschedule-status>
                    Checking availability and updated price…
                </div>

                <label class="mt-4 hidden items-start gap-3 rounded-xl border border-[#ead8a7] bg-[#fff9e9] px-4 py-3 text-[0.78rem] leading-5 text-[#765b22]" data-price-confirmation>
                    <input type="checkbox" name="confirm_price_change" value="1" class="mt-1">
                    <span>I reviewed and confirm the updated price, balance, and any excess payment warning shown in the summary.</span>
                </label>

                <button type="submit" class="mt-5 inline-flex min-h-12 w-full items-center justify-center rounded-xl bg-[#44793b] px-5 text-[0.84rem] font-semibold text-white transition hover:bg-[#396733] disabled:cursor-not-allowed disabled:opacity-50" data-reschedule-submit disabled>
                    Confirm reschedule
                </button>
            </section>

            <aside class="space-y-5">
                <section class="rounded-[1.35rem] border border-[#dfe5db] bg-white p-5 shadow-[0_18px_55px_rgba(26,47,38,0.06)]">
                    <p class="text-[0.68rem] font-bold uppercase tracking-[0.18em] text-[#b39a5a]">Current booking</p>
                    <h2 class="mt-1 text-[1.05rem] font-semibold text-[#173029]">{{ $bookingHeader->reference_no }}</h2>
                    <dl class="mt-4 grid gap-3 text-[0.78rem]">
                        <div class="flex justify-between gap-4"><dt class="text-[#85877f]">Customer</dt><dd class="text-right font-semibold text-[#263f36]">{{ $bookingHeader->customer_name }}</dd></div>
                        <div class="flex justify-between gap-4"><dt class="text-[#85877f]">Room</dt><dd class="text-right font-semibold text-[#263f36]">{{ $currentRoom }}</dd></div>
                        <div class="flex justify-between gap-4"><dt class="text-[#85877f]">Date</dt><dd class="text-right font-semibold text-[#263f36]">{{ $currentDate }}</dd></div>
                        <div class="flex justify-between gap-4"><dt class="text-[#85877f]">Schedule</dt><dd class="text-right font-semibold text-[#263f36]">{{ $currentTime }}</dd></div>
                        <div class="flex justify-between gap-4 border-t border-[#edf1ea] pt-3"><dt class="text-[#85877f]">Current line total</dt><dd class="font-semibold text-[#263f36]">Php {{ number_format((float) $bookingDetail->subtotal, 2) }}</dd></div>
                    </dl>
                </section>

                <section class="rounded-[1.35rem] border border-[#d5e3cc] bg-[#f7fbf1] p-5" data-price-preview>
                    <p class="text-[0.68rem] font-bold uppercase tracking-[0.18em] text-[#6a8a57]">Updated summary</p>
                    <p class="mt-3 text-[0.8rem] text-[#71806f]" data-preview-placeholder>Select a valid schedule to see the recalculated totals.</p>
                    <dl class="mt-4 hidden gap-3 text-[0.78rem]" data-preview-values>
                        <div class="flex justify-between gap-4"><dt class="text-[#71806f]">New line total</dt><dd class="font-semibold text-[#274a31]" data-preview-line>—</dd></div>
                        <div class="flex justify-between gap-4"><dt class="text-[#71806f]">Price difference</dt><dd class="font-semibold text-[#274a31]" data-preview-difference>—</dd></div>
                        <div class="flex justify-between gap-4"><dt class="text-[#71806f]">Updated booking total</dt><dd class="font-semibold text-[#274a31]" data-preview-total>—</dd></div>
                        <div class="flex justify-between gap-4"><dt class="text-[#71806f]">Approved payments</dt><dd class="font-semibold text-[#274a31]" data-preview-paid>—</dd></div>
                        <div class="flex justify-between gap-4 border-t border-[#d5e3cc] pt-3"><dt class="text-[#71806f]">New balance</dt><dd class="text-[0.95rem] font-bold text-[#274a31]" data-preview-balance>—</dd></div>
                        <div class="hidden rounded-lg bg-[#fff1cc] px-3 py-2 text-[#765b22]" data-preview-overpayment></div>
                    </dl>
                </section>

                <p class="rounded-xl border border-[#e4e8df] bg-white px-4 py-3 text-[0.75rem] leading-5 text-[#7a7d75]">The system will recheck availability inside a locked transaction before saving. The Reschedule button expires automatically once the original booking start time arrives.</p>
            </aside>
        </form>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const form = document.querySelector('[data-admin-reschedule-form]');

            if (!form) return;

            const status = form.querySelector('[data-reschedule-status]');
            const submit = form.querySelector('[data-reschedule-submit]');
            const confirmation = form.querySelector('[data-price-confirmation]');
            const confirmationInput = confirmation.querySelector('input');
            const placeholder = form.querySelector('[data-preview-placeholder]');
            const values = form.querySelector('[data-preview-values]');
            const isLongStay = form.dataset.isLongStay === '1';
            const startInput = form.querySelector('[data-selected-start]');
            const endInput = form.querySelector('[data-selected-end]');
            const startSlots = form.querySelector('[data-start-slots]');
            const endSlots = form.querySelector('[data-end-slots]');
            const endHelp = form.querySelector('[data-end-help]');
            const money = (amount) => `Php ${Number(amount || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
            let previewTimer;

            const setPreview = (payload) => {
                placeholder.classList.add('hidden');
                values.classList.remove('hidden');
                values.classList.add('grid');
                form.querySelector('[data-preview-line]').textContent = money(payload.new_line_total);
                const difference = Number(payload.price_difference || 0);
                form.querySelector('[data-preview-difference]').textContent = `${difference > 0 ? '+' : difference < 0 ? '-' : ''}${money(Math.abs(difference))}`;
                form.querySelector('[data-preview-total]').textContent = money(payload.new_total);
                form.querySelector('[data-preview-paid]').textContent = money(payload.approved_total);
                form.querySelector('[data-preview-balance]').textContent = money(payload.new_balance);

                const overpayment = form.querySelector('[data-preview-overpayment]');
                if (Number(payload.overpayment || 0) > 0) {
                    overpayment.textContent = `${money(payload.overpayment)} excess payment requires admin follow-up.`;
                    overpayment.classList.remove('hidden');
                } else {
                    overpayment.classList.add('hidden');
                }

                confirmation.classList.toggle('hidden', !payload.requires_price_confirmation);
                confirmation.classList.toggle('flex', payload.requires_price_confirmation);
                confirmationInput.required = Boolean(payload.requires_price_confirmation);
                if (!payload.requires_price_confirmation) confirmationInput.checked = false;
            };

            const preview = async () => {
                confirmationInput.required = false;

                if (!form.checkValidity()) {
                    submit.disabled = true;
                    return;
                }

                status.textContent = 'Checking availability and updated price...';
                status.className = 'mt-5 rounded-xl border border-[#e4e8df] bg-[#f9faf6] px-4 py-3 text-[0.78rem] text-[#68736b]';
                submit.disabled = true;

                const payload = new FormData(form);
                payload.delete('_method');
                payload.delete('confirm_price_change');

                try {
                    const response = await fetch(form.dataset.previewUrl, {
                        method: 'POST',
                        headers: { 'Accept': 'application/json' },
                        body: payload,
                    });
                    const result = await response.json();

                    if (!response.ok) {
                        const errors = result.errors ? Object.values(result.errors).flat() : [result.message || 'This schedule is unavailable.'];
                        throw new Error(errors[0]);
                    }

                    setPreview(result);
                    status.textContent = `Available - ${result.rate_name}`;
                    status.className = 'mt-5 rounded-xl border border-[#cfe0c3] bg-[#f4faea] px-4 py-3 text-[0.78rem] font-semibold text-[#3f6a34]';
                    submit.disabled = false;
                } catch (error) {
                    status.textContent = error.message || 'This schedule is unavailable.';
                    status.className = 'mt-5 rounded-xl border border-[#efc7bf] bg-[#fff5f3] px-4 py-3 text-[0.78rem] text-[#9f3d2f]';
                    submit.disabled = true;
                    placeholder.classList.remove('hidden');
                    values.classList.add('hidden');
                    values.classList.remove('grid');
                    confirmation.classList.add('hidden');
                    confirmation.classList.remove('flex');
                    confirmationInput.required = false;
                }
            };

            const renderSlotButtons = (container, slots, selectedValue, onSelect, emptyMessage) => {
                container.innerHTML = '';

                if (!Array.isArray(slots) || slots.length === 0) {
                    const empty = document.createElement('div');
                    empty.className = 'col-span-full rounded-xl border border-dashed border-[#dfe5db] px-4 py-5 text-center text-[0.76rem] text-[#92958d]';
                    empty.textContent = emptyMessage;
                    container.appendChild(empty);
                    return;
                }

                slots.forEach((slot) => {
                    const button = document.createElement('button');
                    const selected = slot.available && slot.value === selectedValue;
                    button.type = 'button';
                    button.disabled = !slot.available;
                    button.dataset.slotValue = slot.value;
                    button.className = selected
                        ? 'min-h-14 rounded-xl border border-[#44793b] bg-[#44793b] px-2 py-2 text-center text-[0.78rem] font-semibold text-white shadow-[0_8px_20px_rgba(68,121,59,0.2)]'
                        : slot.available
                            ? 'min-h-14 rounded-xl border border-[#d7e0d1] bg-white px-2 py-2 text-center text-[0.78rem] font-semibold text-[#31503f] transition hover:border-[#6f9659] hover:bg-[#f4faea]'
                            : 'min-h-14 cursor-not-allowed rounded-xl border border-[#eceee9] bg-[#f5f5f2] px-2 py-2 text-center text-[0.76rem] text-[#aaaDA7] opacity-75';

                    const label = document.createElement('span');
                    label.className = 'block';
                    label.textContent = slot.label;
                    button.appendChild(label);

                    if (!slot.available) {
                        const reason = document.createElement('small');
                        reason.className = 'mt-0.5 block text-[0.62rem] font-medium uppercase tracking-[0.08em] text-[#b0b2ac]';
                        reason.textContent = slot.reason || 'Unavailable';
                        button.appendChild(reason);
                    }

                    if (slot.available) button.addEventListener('click', () => onSelect(slot));
                    container.appendChild(button);
                });
            };

            const fetchSlots = async (selectedStart = null) => {
                const payload = new FormData();
                payload.append('_token', form.querySelector('[name="_token"]').value);
                payload.append('hyve_room_id', form.querySelector('[name="hyve_room_id"]').value);
                payload.append('booking_date', form.querySelector('[name="booking_date"]').value);
                if (selectedStart) payload.append('start_time', selectedStart);

                const response = await fetch(form.dataset.slotsUrl, {
                    method: 'POST',
                    headers: { 'Accept': 'application/json' },
                    body: payload,
                });
                const result = await response.json();

                if (!response.ok) {
                    const errors = result.errors ? Object.values(result.errors).flat() : [result.message || 'Unable to load time slots.'];
                    throw new Error(errors[0]);
                }

                return result;
            };

            const loadEndSlots = async (startValue, preferredEnd = '') => {
                startInput.value = startValue;
                endInput.value = '';
                submit.disabled = true;
                endHelp.textContent = 'Loading valid end times...';
                endSlots.innerHTML = '<div class="col-span-full rounded-xl border border-dashed border-[#dfe5db] px-4 py-5 text-center text-[0.76rem] text-[#92958d]">Loading valid end times...</div>';

                try {
                    const result = await fetchSlots(startValue);
                    renderSlotButtons(startSlots, result.start_times, startValue, (slot) => loadEndSlots(slot.value), 'No start times are available for this date.');
                    const preferredAvailable = result.end_times.some((slot) => slot.available && slot.value === preferredEnd);
                    const selectedEnd = preferredAvailable ? preferredEnd : '';
                    endInput.value = selectedEnd;
                    const renderEndOptions = (selectedValue = '') => {
                        renderSlotButtons(endSlots, result.end_times, selectedValue, (slot) => {
                            endInput.value = slot.value;
                            renderEndOptions(slot.value);
                            const hours = Number(slot.duration_minutes || 0) / 60;
                            endHelp.textContent = `${hours.toLocaleString(undefined, { maximumFractionDigits: 1 })} hour${hours === 1 ? '' : 's'} selected.`;
                            preview();
                        }, 'No valid end times are available for this start time.');
                    };
                    renderEndOptions(selectedEnd);

                    if (selectedEnd) {
                        const selectedSlot = result.end_times.find((slot) => slot.value === selectedEnd);
                        const hours = Number(selectedSlot?.duration_minutes || 0) / 60;
                        endHelp.textContent = `${hours.toLocaleString(undefined, { maximumFractionDigits: 1 })} hour${hours === 1 ? '' : 's'} selected.`;
                        preview();
                    } else {
                        endHelp.textContent = 'Choose one of the valid end times below.';
                    }
                } catch (error) {
                    endHelp.textContent = error.message || 'Unable to load valid end times.';
                    renderSlotButtons(endSlots, [], '', () => {}, 'No valid end times are available.');
                }
            };

            const loadStartSlots = async (preserveCurrent = false) => {
                const preferredStart = preserveCurrent ? startInput.value : '';
                const preferredEnd = preserveCurrent ? endInput.value : '';
                if (!preserveCurrent) {
                    startInput.value = '';
                    endInput.value = '';
                }
                submit.disabled = true;
                startSlots.innerHTML = '<div class="col-span-full rounded-xl border border-dashed border-[#dfe5db] px-4 py-5 text-center text-[0.76rem] text-[#92958d]">Loading available start times...</div>';

                try {
                    const result = await fetchSlots();
                    const preferredAvailable = result.start_times.some((slot) => slot.available && slot.value === preferredStart);
                    renderSlotButtons(startSlots, result.start_times, preferredAvailable ? preferredStart : '', (slot) => loadEndSlots(slot.value), 'No start times are available for this date.');

                    if (preferredAvailable) {
                        await loadEndSlots(preferredStart, preferredEnd);
                    } else {
                        renderSlotButtons(endSlots, [], '', () => {}, 'Select an available start time first.');
                        endHelp.textContent = 'Select a start time first.';
                        status.textContent = 'Choose an available start and end time.';
                    }
                } catch (error) {
                    renderSlotButtons(startSlots, [], '', () => {}, error.message || 'Unable to load time slots.');
                    renderSlotButtons(endSlots, [], '', () => {}, 'Select an available start time first.');
                    status.textContent = error.message || 'Unable to load time slots.';
                    submit.disabled = true;
                }
            };

            form.querySelectorAll('select, input[type="date"]').forEach((field) => {
                field.addEventListener('change', () => {
                    if (field.name === 'booking_date') {
                        const endDate = form.querySelector('[name="booking_end_date"]');
                        if (endDate) endDate.min = field.value;
                    }
                    clearTimeout(previewTimer);
                    previewTimer = setTimeout(() => isLongStay ? preview() : loadStartSlots(false), 180);
                });
            });

            if (isLongStay) {
                preview();
            } else {
                loadStartSlots(true);
            }
        });
    </script>
@endsection
