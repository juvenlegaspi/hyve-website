<?php

namespace App\Http\Controllers;

use App\Models\BookingActivity;
use App\Models\BookingHeader;
use App\Models\BookingDetail;
use App\Models\BookingPayment;
use App\Models\HyveCalendarEvent;
use App\Models\HyveRoom;
use App\Models\PaymentSetting;
use App\Models\Space;
use App\Models\HyveScheduleOverride;
use App\Services\BookingWifiVoucherService;
use App\Support\HyvePricing;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MemberPortalController extends Controller
{
    public function __construct(
        private readonly BookingWifiVoucherService $wifiVoucherService,
        private readonly HyvePricing $pricing,
    ) {
    }

    public function bookings(Request $request): View|RedirectResponse
    {
        if ($request->user()?->isAdmin()) {
            return redirect()->route('admin.dashboard');
        }

        $memberData = $this->memberData($request);

        return view('member.bookings', [
            'meta' => [
                'title' => 'My Bookings | HYVE Workspace',
                'description' => 'Manage your HYVE member profile, review booking history, and update your account details.',
            ],
            ...$memberData,
        ]);
    }

    public function account(Request $request): View|RedirectResponse
    {
        if ($request->user()?->isAdmin()) {
            return redirect()->route('admin.dashboard');
        }

        return $this->profile($request);
    }

    public function profile(Request $request): View|RedirectResponse
    {
        if ($request->user()?->isAdmin()) {
            return redirect()->route('admin.dashboard');
        }

        return view('member.profile', [
            'meta' => [
                'title' => 'Edit Profile | HYVE Workspace',
                'description' => 'Update your HYVE member profile details in a clean dedicated workspace.',
            ],
            ...$this->memberData($request),
        ]);
    }

    public function password(Request $request): View|RedirectResponse
    {
        if ($request->user()?->isAdmin()) {
            return redirect()->route('admin.dashboard');
        }

        return view('member.password', [
            'meta' => [
                'title' => 'Change Password | HYVE Workspace',
                'description' => 'Change your HYVE member password in a separate secure page.',
            ],
            ...$this->memberData($request),
        ]);
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'username' => ['required', 'string', 'max:100', Rule::unique('booking_users', 'username')->ignore($user->id)],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', Rule::unique('booking_users', 'email')->ignore($user->id)],
            'phone' => ['required', 'string', 'max:30'],
        ]);

        $user->update([
            'username' => trim(strtolower($validated['username'])),
            'first_name' => trim($validated['first_name']),
            'last_name' => trim($validated['last_name']),
            'email' => trim(strtolower($validated['email'])),
            'number' => trim($validated['phone']),
        ]);

        return back()->with('member_success', 'Your profile details were updated.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user->update([
            'password' => Hash::make($validated['password']),
        ]);

        return back()->with('member_success', 'Your password was updated.');
    }

    public function balancePayment(Request $request, BookingHeader $bookingHeader): View|RedirectResponse
    {
        abort_unless(
            $bookingHeader->booking_type === BookingHeader::TYPE_MEMBER
                && (int) $bookingHeader->user_id === (int) $request->user()->id,
            403
        );

        $bookingHeader->load(['details.hyveRoom', 'details.space']);

        if ($this->hasPendingBalancePayment($bookingHeader)) {
            return redirect()->route('member.index')->with('member_success', 'You already have a pending balance payment for this booking. Please wait for admin review first.');
        }

        $selectedHeaderSummary = $this->headerSummary($bookingHeader);
        $selectedDetailId = (int) $request->integer('detail');
        $selectedItem = collect($selectedHeaderSummary['items'])->firstWhere('detail_id', $selectedDetailId)
            ?? collect($selectedHeaderSummary['items'])->first();
        $selectedPaymentAmount = min(
            (float) ($selectedItem['amount'] ?? 0),
            (float) ($selectedHeaderSummary['balance_amount'] ?? 0)
        );
        $selectedPaymentAmount = round(max(0.01, $selectedPaymentAmount), 2);

        return view('member.balance-payment', [
            'meta' => [
                'title' => 'Pay Remaining Balance | HYVE Workspace',
                'description' => 'Complete the remaining balance for your HYVE room booking.',
            ],
            'selectedHeader' => $bookingHeader,
            'selectedHeaderSummary' => $selectedHeaderSummary,
            'selectedBalanceItem' => $selectedItem,
            'selectedPaymentAmount' => $selectedPaymentAmount,
            'paymentSetting' => PaymentSetting::query()->active()->latest('updated_at')->first(),
            ...$this->memberData($request),
        ]);
    }

    public function reschedule(Request $request, BookingDetail $bookingDetail): View|RedirectResponse
    {
        $bookingDetail = $this->memberOwnedBookingDetail($request, $bookingDetail);
        $bookingHeader = $bookingDetail->bookingHeader;

        if (! $this->canRescheduleDetail($bookingDetail)) {
            return redirect()->route('member.index')->with('member_success', 'This booking can no longer be rescheduled because it is already within 24 hours of the reserved schedule.');
        }

        $allRooms = HyveRoom::query()->active()->orderBy('id')->get();
        $sharedTableRepresentative = $this->sharedTableRepresentative($allRooms);
        $displayRooms = $this->displayBookingRooms($allRooms, $sharedTableRepresentative);
        $selectedRoomId = old(
            'hyve_room_id',
            (string) ($bookingDetail->hyveRoom?->isSharedTable() ? $sharedTableRepresentative?->id : $bookingDetail->hyve_room_id)
        );
        $selectedRoom = $displayRooms->first(fn (HyveRoom $room): bool => (int) $room->id === (int) $selectedRoomId)
            ?? $displayRooms->first();
        $selectedRoomForPricing = $selectedRoom ? $this->pricingRoomForSelection($selectedRoom, $sharedTableRepresentative) : null;
        $selectedQuote = null;

        if ($selectedRoomForPricing) {
            if ($this->isLongStayDetail($bookingDetail)) {
                $selectedQuote = $this->pricing->quoteForLongStayRoom(
                    $selectedRoomForPricing,
                    '',
                    old('booking_date', $this->detailDateValue($bookingDetail->booking_date)?->toDateString() ?? now()->toDateString()),
                    old(
                        'booking_end_date',
                        ($this->detailDateValue($bookingDetail->booking_end_date) ?: $this->detailDateValue($bookingDetail->booking_date))?->toDateString() ?? now()->toDateString()
                    ),
                );
            } else {
                $selectedQuote = $this->pricing->quoteForRoom(
                    $selectedRoomForPricing,
                    old('booking_date', $this->detailDateValue($bookingDetail->booking_date)?->toDateString() ?? now()->toDateString()),
                    old('start_time', substr((string) $bookingDetail->start_time, 0, 5)),
                    old('end_time', substr((string) $bookingDetail->end_time, 0, 5)),
                );
            }
        }

        return view('member.reschedule-booking', [
            'meta' => [
                'title' => 'Reschedule Booking | HYVE Workspace',
                'description' => 'Move your existing HYVE booking to a new room, date, or time without creating a new payment record.',
            ],
            'bookingDetail' => $bookingDetail,
            'bookingHeader' => $bookingHeader,
            'displayRooms' => $displayRooms,
            'sharedTableRepresentative' => $sharedTableRepresentative,
            'selectedRoom' => $selectedRoom,
            'selectedQuote' => $selectedQuote,
            'isLongStay' => $this->isLongStayDetail($bookingDetail),
            ...$this->memberData($request),
        ]);
    }

    public function submitReschedule(Request $request, BookingDetail $bookingDetail): RedirectResponse
    {
        $bookingDetail = $this->memberOwnedBookingDetail($request, $bookingDetail);
        $bookingHeader = $bookingDetail->bookingHeader;

        if (! $this->canRescheduleDetail($bookingDetail)) {
            return redirect()->route('member.index')->with('member_success', 'This booking can no longer be rescheduled because it is already within 24 hours of the reserved schedule.');
        }

        $isLongStay = $this->isLongStayDetail($bookingDetail);
        $validated = $request->validate($this->rescheduleRules($isLongStay), [], [
            'hyve_room_id' => 'room',
            'booking_date' => 'start date',
            'booking_end_date' => 'end date',
            'start_time' => 'start time',
            'end_time' => 'end time',
        ]);

        $allRooms = HyveRoom::query()->active()->orderBy('id')->get();
        $sharedTableRepresentative = $this->sharedTableRepresentative($allRooms);
        $selectedRoom = $allRooms->firstWhere('id', (int) $validated['hyve_room_id']);

        if (! $selectedRoom) {
            return back()->withInput()->withErrors(['hyve_room_id' => 'The selected room is no longer available.']);
        }

        $bookingDate = (string) $validated['booking_date'];
        $bookingEndDate = $isLongStay
            ? (string) $validated['booking_end_date']
            : $bookingDate;
        $startTime = $isLongStay ? '00:00' : (string) $validated['start_time'];
        $endTime = $isLongStay ? '23:59' : (string) $validated['end_time'];
        $bookableRoom = $isLongStay
            ? ($selectedRoom->isSharedTable()
                ? $this->resolveBookableRoomForSelection($selectedRoom, $bookingDate, '00:00', '23:59', $bookingDetail->id)
                : $selectedRoom)
            : $this->resolveBookableRoomForSelection($selectedRoom, $bookingDate, $startTime, $endTime, $bookingDetail->id);

        if (! $bookableRoom) {
            return back()->withInput()->withErrors([
                $isLongStay ? 'booking_end_date' : 'end_time' => 'The selected room, date, or time is no longer available. Please choose another schedule.',
            ]);
        }

        $pricingRoom = $this->pricingRoomForSelection($selectedRoom, $sharedTableRepresentative);
        $quote = $isLongStay
            ? $this->pricing->quoteForLongStayRoom($pricingRoom, '', $bookingDate, $bookingEndDate)
            : $this->pricing->quoteForRoom($pricingRoom, $bookingDate, $startTime, $endTime);

        if (! $quote) {
            return back()->withInput()->withErrors([
                $isLongStay ? 'booking_end_date' : 'end_time' => 'HYVE could not compute the updated rate for this schedule yet.',
            ]);
        }

        if ($isLongStay && ! $this->isLongStayDateRangeAvailable($selectedRoom, $bookingDate, $bookingEndDate, $bookingDetail->id)) {
            return back()->withInput()->withErrors([
                'booking_end_date' => 'The selected stay dates are no longer available. Please choose another date range.',
            ]);
        }

        if (! $isLongStay && ! $this->isTimeRangeAvailable($selectedRoom, $bookingDate, $startTime, $endTime, $bookingDetail->id)) {
            return back()->withInput()->withErrors([
                'end_time' => 'The selected room or time range is no longer available. Please choose another one.',
            ]);
        }

        DB::transaction(function () use ($request, $bookingDetail, $bookingHeader, $bookableRoom, $quote, $bookingDate, $bookingEndDate, $startTime, $endTime, $isLongStay): void {
            $oldSchedule = $this->detailDateLabel($bookingDetail).' | '.$this->detailTimeLabel($bookingDetail);
            $space = $this->spaceForRoom($bookableRoom);

            $bookingDetail->update([
                'space_id' => $space->id,
                'hyve_room_id' => $bookableRoom->id,
                'booking_date' => $bookingDate,
                'booking_end_date' => $bookingEndDate,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'charge_period' => (string) $quote['charge_period'],
                'duration_hours' => (float) ($quote['duration_hours'] ?? 0),
                'billed_hours' => (float) ($quote['billed_hours'] ?? 0),
                'rate_name' => (string) $quote['rate_name'],
                'rate_amount' => (float) ($quote['succeeding_hour_rate'] ?? $quote['minimum_rate'] ?? 0),
                'subtotal' => (float) ($quote['total_amount'] ?? 0),
            ]);

            $bookingHeader->refresh();
            $bookingHeader->load('details');

            $newTotal = round((float) $bookingHeader->details->sum(fn (BookingDetail $detail): float => (float) ($detail->subtotal ?? 0)), 2);
            $discountSnapshot = $bookingHeader->discountSnapshotFor($newTotal);
            $paidSoFar = round((float) ($bookingHeader->downpayment_amount ?? 0), 2);
            $newBalance = round(max(0, (float) $discountSnapshot['discounted_total_amount'] - $paidSoFar), 2);
            $currentPaymentStatus = strtolower((string) ($bookingHeader->payment_status ?? ''));
            $newPaymentStatus = $bookingHeader->payment_status;

            if ($newBalance <= 0) {
                $newPaymentStatus = in_array($currentPaymentStatus, ['pending_verification', 'pending_balance_verification'], true)
                    ? $bookingHeader->payment_status
                    : 'paid';
            } elseif ($currentPaymentStatus === 'paid') {
                $newPaymentStatus = 'partially_paid';
            }

            $bookingHeader->update([
                'total_amount' => $newTotal,
                'discount_amount' => $discountSnapshot['discount_amount'],
                'discounted_total_amount' => $discountSnapshot['discounted_total_amount'],
                'balance_amount' => $newBalance,
                'payment_status' => $newPaymentStatus,
            ]);

            if (Schema::hasTable('booking_activities')) {
                BookingActivity::query()->create([
                    'booking_header_id' => $bookingHeader->getKey(),
                    'booking_detail_id' => $bookingDetail->getKey(),
                    'actor_user_id' => $request->user()?->getKey(),
                    'event_key' => 'booking_rescheduled',
                    'event_label' => 'Booking rescheduled',
                    'reference_no' => $bookingHeader->reference_no,
                    'customer_name' => $bookingHeader->customer_name,
                    'room_name' => $bookableRoom->room_name,
                    'booking_date' => $bookingDetail->fresh()->booking_date,
                    'time_range' => $this->detailTimeLabel($bookingDetail->fresh()),
                    'message' => 'Member rescheduled booking from '.$oldSchedule.' to '.$this->detailDateLabel($bookingDetail->fresh()).' | '.$this->detailTimeLabel($bookingDetail->fresh()).'.',
                ]);
            }
        });

        $freshHeader = $bookingHeader->fresh(['details', 'wifiVoucher']);

        if ((string) $freshHeader->status === 'confirmed') {
            $this->wifiVoucherService->ensureVoucherForBooking($freshHeader);
        }

        return redirect()->route('member.index')->with('member_success', 'Booking rescheduled successfully. Your existing payment stays attached to the same booking.');
    }

    public function submitBalancePayment(Request $request, BookingHeader $bookingHeader): RedirectResponse
    {
        abort_unless(
            $bookingHeader->booking_type === BookingHeader::TYPE_MEMBER
                && (int) $bookingHeader->user_id === (int) $request->user()->id,
            403
        );

        if ((float) ($bookingHeader->balance_amount ?? 0) <= 0) {
            return redirect()->route('member.index')->with('member_success', 'This booking no longer has a remaining balance.');
        }

        if ($this->hasPendingBalancePayment($bookingHeader)) {
            return redirect()->route('member.index')->with('member_success', 'You already submitted a balance payment for this booking. Please wait for admin review.');
        }

        $validated = $request->validate([
            'detail_id' => ['nullable', 'integer'],
            'payment_scope' => ['nullable', Rule::in(['single', 'all'])],
            'payment_amount' => ['nullable', 'numeric', 'gt:0'],
            'payment_method' => ['required', Rule::in(['gcash', 'bank_transfer'])],
            'rules_agreement' => ['required', 'accepted'],
            'payment_proof' => ['required', 'file', 'mimetypes:image/jpeg,image/png,image/gif', 'max:5120'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $bookingHeader->load(['details.hyveRoom', 'details.space']);
        $summary = $this->headerSummary($bookingHeader);
        $selectedItem = collect($summary['items'])->firstWhere('detail_id', (int) ($validated['detail_id'] ?? 0))
            ?? collect($summary['items'])->first();
        $currentBalance = round((float) ($bookingHeader->balance_amount ?? 0), 2);
        $paymentScope = (string) ($validated['payment_scope'] ?? 'single');
        $defaultPaymentAmount = $paymentScope === 'all'
            ? $currentBalance
            : min(
                round((float) ($selectedItem['amount'] ?? $currentBalance), 2),
                $currentBalance
            );
        $paymentAmount = array_key_exists('payment_amount', $validated) && $validated['payment_amount'] !== null
            ? round((float) $validated['payment_amount'], 2)
            : round($defaultPaymentAmount, 2);

        if ($paymentAmount <= 0 || $paymentAmount > $currentBalance) {
            return back()->withErrors([
                'payment_amount' => 'Enter a valid payment amount that does not exceed the remaining balance.',
            ]);
        }

        $paymentProofPath = $request->file('payment_proof')?->store('booking-payments', 'public');
        $paymentProofName = $request->file('payment_proof')?->getClientOriginalName();
        $newNotes = trim((string) ($validated['notes'] ?? ''));
        $paymentNote = 'Submitted booking payment on '.now()->format('F j, Y g:i A')
            .' for Php '.number_format($paymentAmount, 2).'.';

        DB::transaction(function () use ($request, $bookingHeader, $selectedItem, $validated, $paymentAmount, $paymentProofPath, $paymentProofName, $paymentNote, $newNotes, $currentBalance): void {
            BookingPayment::query()->create([
                'booking_header_id' => $bookingHeader->getKey(),
                'booking_detail_id' => (int) ($selectedItem['detail_id'] ?? 0) > 0 ? (int) $selectedItem['detail_id'] : null,
                'user_id' => $request->user()?->id,
                'payment_type' => BookingPayment::TYPE_BALANCE,
                'amount' => $paymentAmount,
                'payment_method' => $validated['payment_method'],
                'status' => BookingPayment::STATUS_PENDING,
                'payment_proof_path' => $paymentProofPath,
                'payment_proof_name' => $paymentProofName,
                'notes' => collect([$paymentNote, $newNotes])
                    ->filter(fn ($value) => $value !== '')
                    ->implode(PHP_EOL.PHP_EOL),
                'paid_at' => now(),
            ]);

            $combinedNotes = collect([(string) ($bookingHeader->notes ?? ''), $paymentNote, $newNotes])
                ->map(fn ($value) => trim((string) $value))
                ->filter(fn ($value) => $value !== '')
                ->implode(PHP_EOL.PHP_EOL);

            $bookingHeader->update([
                'payment_method' => $validated['payment_method'],
                'payment_status' => 'pending_balance_verification',
                'payment_proof_path' => $paymentProofPath,
                'payment_proof_name' => $paymentProofName,
                'downpayment_amount' => round((float) ($bookingHeader->downpayment_amount ?? 0) + $paymentAmount, 2),
                'balance_amount' => round(max(0, $currentBalance - $paymentAmount), 2),
                'notes' => $combinedNotes,
            ]);
        });

        return redirect()->route('member.index')->with('member_success', 'Remaining balance submitted successfully. HYVE will verify your payment shortly.');
    }

    public function cancelBooking(Request $request, BookingHeader $bookingHeader): RedirectResponse
    {
        abort_unless(
            $bookingHeader->booking_type === BookingHeader::TYPE_MEMBER
                && (int) $bookingHeader->user_id === (int) $request->user()->id,
            403
        );

        $bookingHeader->load('details');

        $hasUpcomingSlot = $bookingHeader->details->contains(function ($detail) {
            if (! $detail->booking_date) {
                return false;
            }

            $end = Carbon::createFromFormat('H:i:s', $detail->end_time);
            $endAt = $detail->booking_date->copy()->setTime($end->hour, $end->minute, $end->second);

            return $endAt->greaterThanOrEqualTo(now());
        });

        if (! $hasUpcomingSlot || strtolower((string) $bookingHeader->status) === 'cancelled') {
            return back()->with('member_success', 'This booking can no longer be cancelled.');
        }

        DB::transaction(function () use ($bookingHeader) {
            $bookingHeader->update([
                'status' => 'cancelled',
            ]);

            $bookingHeader->details()->update([
                'status' => 'cancelled',
            ]);
        });

        $this->wifiVoucherService->revokeVoucherForBooking($bookingHeader->fresh('wifiVoucher'));

        return back()->with('member_success', 'Booking cancelled. Downpayments for cancelled bookings are non-refundable.');
    }

    /**
     * @return array<string, mixed>
     */
    private function memberData(Request $request): array
    {
        $user = $request->user();

        $bookingHeaders = BookingHeader::query()
            ->with(['details.bookingHeader', 'details.hyveRoom', 'details.space', 'payments', 'wifiVoucher'])
            ->where('booking_type', BookingHeader::TYPE_MEMBER)
            ->where('user_id', $user->id)
            ->latest('created_at')
            ->get();

        $bookingDetails = $bookingHeaders
            ->flatMap(fn (BookingHeader $header) => $header->details)
            ->sortByDesc(fn ($detail) => optional($detail->booking_date)->timestamp ?? 0)
            ->values();

        $now = now();
        $bookingCards = $bookingDetails
            ->map(function ($detail) use ($now) {
                $header = $detail->bookingHeader;
                $bookingDate = $detail->booking_date;
                $isLongStay = $this->isLongStayDetail($detail);
                $start = $this->timeFromDatabase((string) $detail->start_time);
                $end = $this->timeFromDatabase((string) $detail->end_time);
                $endDate = $detail->booking_end_date ?: $bookingDate;
                $startAt = $bookingDate
                    ? ($isLongStay
                        ? $bookingDate->copy()->startOfDay()
                        : $bookingDate->copy()->setTime($start->hour, $start->minute, $start->second))
                    : null;
                $endAt = $isLongStay
                    ? optional($endDate)?->copy()->endOfDay()
                    : ($bookingDate
                        ? $bookingDate->copy()->setTime($end->hour, $end->minute, $end->second)
                        : null);

                $status = strtolower((string) ($header->status ?? BookingHeader::STATUS_PENDING));
                $statusLabel = match ($status) {
                    'confirmed' => 'Confirmed',
                    'cancelled' => 'Cancelled',
                    'completed' => 'Completed',
                    default => 'Pending',
                };

                $statusMeta = match ($status) {
                    'confirmed' => 'Ready for your scheduled visit',
                    'cancelled' => 'Reservation was cancelled',
                    'completed' => 'Finished booking',
                    default => 'Awaiting final review',
                };

                $paymentMethod = match (strtolower((string) ($header->payment_method ?? ''))) {
                    'gcash' => 'GCash',
                    'maya' => 'Maya',
                    'card' => 'Card',
                    'bank_transfer' => 'Bank transfer',
                    default => 'Direct payment',
                };

                $hasPendingBalancePayment = $header->payments
                    ->where('payment_type', BookingPayment::TYPE_BALANCE)
                    ->where('status', BookingPayment::STATUS_PENDING)
                    ->isNotEmpty();

                if ($hasPendingBalancePayment && $status !== 'cancelled') {
                    $statusMeta = 'Balance payment submitted and waiting for admin verification';
                }

                return [
                    'reference_no' => $header->reference_no,
                    'booking_header_id' => $header->id,
                    'booking_detail_id' => $detail->id,
                    'room_name' => $detail->hyveRoom?->room_name ?? $detail->space?->name ?? 'Space booking',
                    'space_label' => $detail->hyveRoom?->mappedSpaceLabel() ?? $detail->space?->name ?? 'HYVE Workspace',
                    'booking_date' => $bookingDate,
                    'booking_end_date' => $endDate,
                    'display_date' => $this->detailDateLabel($detail),
                    'start_label' => $isLongStay ? 'Start date' : $start->format('g:i A'),
                    'end_label' => $isLongStay ? 'End date' : $end->format('g:i A'),
                    'time_label' => $this->detailTimeLabel($detail),
                    'duration_label' => $this->detailDurationLabel($detail),
                    'amount' => (float) ($detail->subtotal ?? 0),
                    'downpayment_amount' => (float) ($header->downpayment_amount ?? 0),
                    'remaining_balance' => (float) ($header->balance_amount ?? 0),
                    'payment_badge_label' => ((float) ($header->balance_amount ?? 0) <= 0)
                        ? 'Paid'
                        : ($hasPendingBalancePayment ? 'Payment submitted' : 'With balance'),
                    'payment_badge_class' => ((float) ($header->balance_amount ?? 0) <= 0)
                        ? 'is-paid'
                        : 'is-balance',
                    'payment_method' => $paymentMethod,
                    'status_label' => $statusLabel,
                    'status_meta' => $statusMeta,
                    'status_class' => match ($status) {
                        'confirmed', 'completed' => 'is-confirmed',
                        'cancelled' => 'is-cancelled',
                        default => 'is-pending',
                    },
                    'is_upcoming' => $endAt ? $endAt->greaterThanOrEqualTo($now) : false,
                    'can_cancel' => $endAt
                        ? $endAt->greaterThanOrEqualTo($now) && in_array($status, ['pending', 'confirmed'], true)
                        : false,
                    'can_pay_balance' => ((float) ($header->balance_amount ?? 0) > 0)
                        && ! $hasPendingBalancePayment
                        && in_array($status, ['pending', 'confirmed'], true),
                    'can_reschedule' => $startAt
                        ? $startAt->greaterThan($now)
                            && $now->lt($startAt->copy()->subHours(24))
                            && in_array($status, ['pending', 'confirmed'], true)
                        : false,
                    'reschedule_url' => route('member.bookings.reschedule', $detail),
                    'has_pending_balance_payment' => $hasPendingBalancePayment,
                    'is_long_stay' => $isLongStay,
                    'wifi_voucher' => $this->wifiVoucherService->payloadForBooking($header),
                ];
            })
            ->sortBy([
                ['booking_date', 'desc'],
                ['start_label', 'desc'],
            ])
            ->values();

        return [
            'navigation' => config('hyve.navigation', []),
            'bookingHeaders' => $bookingHeaders,
            'bookingDetails' => $bookingDetails,
            'upcomingBookings' => $bookingCards->filter(fn (array $booking) => $booking['is_upcoming'])->sortBy([
                ['booking_date', 'asc'],
                ['start_label', 'asc'],
            ])->values(),
            'pastBookings' => $bookingCards->reject(fn (array $booking) => $booking['is_upcoming'])->values(),
            'memberStats' => [
                'total_bookings' => $bookingHeaders->count(),
                'total_hours' => (float) $bookingDetails->sum(fn ($detail) => (float) ($detail->duration_hours ?? 0)),
                'upcoming_slots' => $bookingDetails->filter(fn ($detail) => optional($detail->booking_date)?->isToday() || optional($detail->booking_date)?->isFuture())->count(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function headerSummary(BookingHeader $header): array
    {
        $header->loadMissing(['details.hyveRoom', 'details.space']);

        $details = $header->details
            ->sortBy([
                ['booking_date', 'asc'],
                ['start_time', 'asc'],
            ])
            ->values();

        return [
            'reference_no' => $header->reference_no,
            'total_amount' => round((float) ($header->total_amount ?? 0), 2),
            'downpayment_amount' => round((float) ($header->downpayment_amount ?? 0), 2),
            'balance_amount' => round((float) ($header->balance_amount ?? 0), 2),
            'payment_method' => match (strtolower((string) ($header->payment_method ?? ''))) {
                'gcash' => 'GCash',
                'bank_transfer' => 'Bank transfer',
                default => 'Direct payment',
            },
            'items' => $details->map(function ($detail): array {
                return [
                    'detail_id' => $detail->id,
                    'room_name' => $detail->hyveRoom?->room_name ?? $detail->space?->name ?? 'Space booking',
                    'space_label' => $detail->hyveRoom?->mappedSpaceLabel() ?? $detail->space?->name ?? 'HYVE Workspace',
                    'date_label' => $this->detailDateLabel($detail),
                    'time_label' => $this->detailTimeLabel($detail),
                    'amount' => round((float) ($detail->subtotal ?? 0), 2),
                ];
            })->all(),
        ];
    }

    private function isLongStayDetail($detail): bool
    {
        $startDate = $this->detailDateValue($detail->booking_date);
        $endDate = $this->detailDateValue($detail->booking_end_date) ?: $startDate;

        return in_array((string) $detail->charge_period, ['daily', 'weekly', 'monthly'], true)
            || ($startDate && $endDate && $endDate->ne($startDate));
    }

    private function detailDateLabel($detail): string
    {
        $startDate = $this->detailDateValue($detail->booking_date);
        $endDate = $this->detailDateValue($detail->booking_end_date) ?: $startDate;

        if (! $startDate) {
            return '-';
        }

        if ($this->isLongStayDetail($detail) && $endDate && $endDate->ne($startDate)) {
            return $startDate->format('l, F j, Y').' - '.$endDate->format('l, F j, Y');
        }

        return $startDate->format('l, F j, Y');
    }

    private function detailTimeLabel($detail): string
    {
        if ($this->isLongStayDetail($detail)) {
            return match ((string) $detail->charge_period) {
                'monthly' => 'Monthly stay',
                'weekly' => 'Weekly stay',
                'daily' => 'Daily stay',
                default => 'Long stay booking',
            };
        }

        $start = $this->displayBookingTime((string) $detail->start_time);
        $end = $this->displayBookingTime((string) $detail->end_time);

        return $start.' - '.$end;
    }

    private function detailDurationLabel($detail): string
    {
        if ($this->isLongStayDetail($detail)) {
            $startDate = $this->detailDateValue($detail->booking_date);
            $endDate = $this->detailDateValue($detail->booking_end_date) ?: $startDate;

            if (! $startDate || ! $endDate) {
                return 'Long stay';
            }

            $dayCount = max(1, $startDate->diffInDays($endDate) + 1);

            return $dayCount.' day'.($dayCount === 1 ? '' : 's');
        }

        return rtrim(rtrim(number_format((float) ($detail->duration_hours ?? 0), 2), '0'), '.')
            .' hour'.(((float) ($detail->duration_hours ?? 0)) === 1.0 ? '' : 's');
    }

    private function detailDateValue(mixed $value): ?Carbon
    {
        if ($value instanceof Carbon) {
            return $value->copy();
        }

        if ($value === null || $value === '') {
            return null;
        }

        return Carbon::parse((string) $value);
    }

    private function displayBookingTime(string $value): string
    {
        return $this->timeFromDatabase($value)->format('g:i A');
    }

    private function timeFromDatabase(string $value): Carbon
    {
        $format = strlen($value) === 5 ? 'H:i' : 'H:i:s';

        return Carbon::createFromFormat($format, $value);
    }

    private function hasPendingBalancePayment(BookingHeader $bookingHeader): bool
    {
        return $bookingHeader->payments()
            ->where('payment_type', BookingPayment::TYPE_BALANCE)
            ->where('status', BookingPayment::STATUS_PENDING)
            ->exists();
    }

    private function memberOwnedBookingDetail(Request $request, BookingDetail $bookingDetail): BookingDetail
    {
        $bookingDetail->loadMissing(['bookingHeader.payments', 'bookingHeader.wifiVoucher', 'hyveRoom', 'space']);

        abort_unless(
            $bookingDetail->bookingHeader
                && $bookingDetail->bookingHeader->booking_type === BookingHeader::TYPE_MEMBER
                && (int) $bookingDetail->bookingHeader->user_id === (int) $request->user()->id,
            403
        );

        return $bookingDetail;
    }

    private function canRescheduleDetail(BookingDetail $detail): bool
    {
        $headerStatus = strtolower((string) ($detail->bookingHeader?->status ?? BookingHeader::STATUS_PENDING));
        $detailStatus = strtolower((string) ($detail->status ?? BookingDetail::STATUS_PENDING));

        if (! in_array($headerStatus, ['pending', 'confirmed'], true) || ! in_array($detailStatus, ['pending', 'confirmed'], true)) {
            return false;
        }

        $startDate = $this->detailDateValue($detail->booking_date);

        if (! $startDate) {
            return false;
        }

        $startAt = $this->isLongStayDetail($detail)
            ? $startDate->copy()->startOfDay()
            : Carbon::parse($startDate->toDateString().' '.substr((string) $detail->start_time, 0, 5));

        return $startAt->greaterThan(now()) && now()->lt($startAt->copy()->subHours(24));
    }

    /**
     * @return array<string, mixed>
     */
    private function rescheduleRules(bool $isLongStay): array
    {
        $rules = [
            'hyve_room_id' => ['required', 'integer', Rule::exists(HyveRoom::class, 'id')->where(fn ($query) => $query->where('status', 0))],
            'booking_date' => ['required', 'date', 'after_or_equal:today'],
        ];

        if ($isLongStay) {
            $rules['booking_end_date'] = ['required', 'date', 'after_or_equal:booking_date'];

            return $rules;
        }

        $rules['start_time'] = ['required', 'date_format:H:i'];
        $rules['end_time'] = [
            'required',
            'date_format:H:i',
            function (string $attribute, mixed $value, $fail): void {
                $startTime = request()->input('start_time');

                if (! is_string($startTime) || ! is_string($value)) {
                    return;
                }

                $startMinutes = $this->minutesFromTime($startTime);
                $endMinutes = $this->minutesFromTime($value);

                if ($startMinutes === null || $endMinutes === null) {
                    return;
                }

                if ($endMinutes <= $startMinutes) {
                    $endMinutes += 24 * 60;
                }

                $durationMinutes = $endMinutes - $startMinutes;
                $minimumDuration = (int) config('hyve.booking.minimum_duration_minutes', 120);

                if ($durationMinutes < $minimumDuration) {
                    $fail('The end time must be at least '.($minimumDuration / 60).' hours after the start time.');
                }
            },
        ];

        return $rules;
    }

    private function sharedTableRepresentative($rooms): ?HyveRoom
    {
        return collect($rooms)->first(fn (HyveRoom $room): bool => $room->isSharedTable());
    }

    private function displayBookingRooms($rooms, ?HyveRoom $sharedTableRepresentative)
    {
        return collect($rooms)
            ->reject(fn (HyveRoom $room): bool => $room->isSharedTable() && $sharedTableRepresentative && (int) $room->id !== (int) $sharedTableRepresentative->id)
            ->values();
    }

    private function pricingRoomForSelection(HyveRoom $room, ?HyveRoom $sharedTableRepresentative = null): HyveRoom
    {
        if (! $room->isSharedTable()) {
            return $room;
        }

        return $sharedTableRepresentative ?: $this->sharedTableRepresentative(HyveRoom::query()->active()->orderBy('id')->get()) ?: $room;
    }

    private function blockedStatuses(): array
    {
        return (array) config('hyve.booking.blocked_statuses', ['pending', 'confirmed']);
    }

    private function minutesFromTime(string $value): ?int
    {
        [$hour, $minute] = array_pad(explode(':', $value), 2, null);

        if (! is_numeric($hour) || ! is_numeric($minute)) {
            return null;
        }

        return ((int) $hour * 60) + (int) $minute;
    }

    private function slotBoundary(string $bookingDate, string $time): Carbon
    {
        if ($time === '24:00') {
            return Carbon::createFromFormat('Y-m-d H:i', $bookingDate.' 00:00')->addDay();
        }

        return Carbon::createFromFormat('Y-m-d H:i', $bookingDate.' '.$time);
    }

    /**
     * @return array{start: Carbon, end: Carbon}
     */
    private function dateRange(string $bookingDate, string $startTime, string $endTime): array
    {
        $start = $this->slotBoundary($bookingDate, strlen($startTime) === 5 ? $startTime : substr($startTime, 0, 5));
        $end = $this->slotBoundary($bookingDate, strlen($endTime) === 5 ? $endTime : substr($endTime, 0, 5));

        if ($end->lte($start)) {
            $end->addDay();
        }

        return [$start, $end];
    }

    /**
     * @return array{start: Carbon, end: Carbon, closed: bool}
     */
    private function scheduleWindowForDate(string $bookingDate, ?HyveRoom $room = null): array
    {
        $defaultOpeningTime = (string) config('hyve.booking.opening_time', '00:00');
        $defaultClosingTime = (string) config('hyve.booking.closing_time', '24:00');
        $override = $this->scheduleOverrideForRoom($bookingDate, $room);

        if ($override?->isClosed()) {
            return [
                'start' => $this->slotBoundary($bookingDate, $defaultOpeningTime),
                'end' => $this->slotBoundary($bookingDate, $defaultOpeningTime),
                'closed' => true,
            ];
        }

        $openingTime = $override?->isCustom() && $override->opening_time
            ? (string) $override->opening_time
            : $defaultOpeningTime;
        $closingTime = $override?->isCustom() && $override->closing_time
            ? (string) $override->closing_time
            : $defaultClosingTime;

        return [
            'start' => $this->slotBoundary($bookingDate, $openingTime),
            'end' => $this->slotBoundary($bookingDate, $closingTime),
            'closed' => false,
        ];
    }

    private function scheduleOverrideForRoom(string $bookingDate, ?HyveRoom $room = null): ?HyveScheduleOverride
    {
        return HyveScheduleOverride::query()
            ->when($room, function ($query) use ($room) {
                $query->where(function ($builder) use ($room) {
                    $builder->where('hyve_room_id', $room->getKey())
                        ->orWhereNull('hyve_room_id');
                });
            }, fn ($query) => $query->whereNull('hyve_room_id'))
            ->whereDate('booking_date', $bookingDate)
            ->orderByRaw('case when hyve_room_id is null then 1 else 0 end')
            ->first();
    }

    private function applyBookingDateOverlapConstraint($query, string $startDate, string $endDate): void
    {
        $query
            ->whereDate('booking_date', '<=', $endDate)
            ->where(function ($builder) use ($startDate) {
                $builder
                    ->where(function ($sameDay) use ($startDate) {
                        $sameDay->whereNull('booking_end_date')
                            ->whereDate('booking_date', '>=', $startDate);
                    })
                    ->orWhere(function ($range) use ($startDate) {
                        $range->whereNotNull('booking_end_date')
                            ->whereDate('booking_end_date', '>=', $startDate);
                    });
            });
    }

    private function detailOccupiesDate(BookingDetail $detail, string $bookingDate): bool
    {
        $targetDate = Carbon::parse($bookingDate)->startOfDay();
        $startDate = $this->detailDateValue($detail->booking_date)?->startOfDay();
        $endDate = ($this->detailDateValue($detail->booking_end_date) ?: $startDate)?->startOfDay();

        return $startDate !== null
            && $endDate !== null
            && $startDate->lte($targetDate)
            && $endDate->gte($targetDate);
    }

    private function calendarEventsForRoomOnDate(HyveRoom $room, string $bookingDate)
    {
        return HyveCalendarEvent::query()
            ->with('rooms:id,room_name')
            ->active()
            ->forDate($bookingDate)
            ->where('affects_booking', true)
            ->orderBy('start_date')
            ->orderBy('start_time')
            ->get()
            ->filter(fn (HyveCalendarEvent $event): bool => $event->appliesToRoom($room))
            ->values();
    }

    private function isFullDayBlockedByCalendarEvent(HyveRoom $room, string $bookingDate): bool
    {
        return $this->calendarEventsForRoomOnDate($room, $bookingDate)
            ->contains(fn (HyveCalendarEvent $event): bool => $event->isAllDay());
    }

    private function calendarBlockedRangesForRoom(HyveRoom $room, string $bookingDate)
    {
        return $this->calendarEventsForRoomOnDate($room, $bookingDate)
            ->reject(fn (HyveCalendarEvent $event): bool => $event->isAllDay())
            ->map(function (HyveCalendarEvent $event) use ($bookingDate): array {
                [$start, $end] = $this->dateRange($bookingDate, (string) $event->start_time, (string) $event->end_time);

                return ['start' => $start, 'end' => $end];
            })
            ->values();
    }

    private function spaceForRoom(HyveRoom $room): Space
    {
        return Space::query()
            ->active()
            ->where('slug', $room->mappedSpaceSlug())
            ->firstOrFail();
    }

    private function isLongStayDateRangeAvailable(HyveRoom $room, string $startDate, string $endDate, ?int $ignoreDetailId = null): bool
    {
        $space = $this->spaceForRoom($room);
        $query = BookingDetail::query()
            ->whereIn('status', $this->blockedStatuses())
            ->when($ignoreDetailId, fn ($builder) => $builder->whereKeyNot($ignoreDetailId))
            ->where(function ($builder) use ($room, $space) {
                $builder->where('hyve_room_id', $room->id);

                if ($room->isConferenceRoom()) {
                    $builder->orWhere(function ($fallback) use ($space) {
                        $fallback->whereNull('hyve_room_id')
                            ->where('space_id', $space->id);
                    });
                }
            });

        $this->applyBookingDateOverlapConstraint($query, $startDate, $endDate);

        return ! $query->exists();
    }

    private function isTimeRangeAvailable(HyveRoom $room, string $bookingDate, string $startTime, string $endTime, ?int $ignoreDetailId = null): bool
    {
        if ($room->isSharedTable()) {
            return $this->isCommonAreaTimeRangeAvailable($bookingDate, $startTime, $endTime, $ignoreDetailId);
        }

        return $this->isDirectRoomTimeRangeAvailable($room, $bookingDate, $startTime, $endTime, $ignoreDetailId);
    }

    private function isDirectRoomTimeRangeAvailable(HyveRoom $room, string $bookingDate, string $startTime, string $endTime, ?int $ignoreDetailId = null): bool
    {
        [$rangeStart, $rangeEnd] = $this->dateRange($bookingDate, $startTime, $endTime);

        if ($this->isFullDayBlockedByCalendarEvent($room, $bookingDate)) {
            return false;
        }

        $schedule = $this->scheduleWindowForDate($bookingDate, $room);

        if ($schedule['closed']) {
            return false;
        }

        $minimumDuration = (int) config('hyve.booking.minimum_duration_minutes', 120);
        $intervalMinutes = (int) config('hyve.booking.slot_interval_minutes', 30);

        if ($rangeStart->lt($schedule['start']) || $rangeEnd->gt($schedule['end'])) {
            return false;
        }

        if ($rangeStart->diffInMinutes($rangeEnd, true) < $minimumDuration) {
            return false;
        }

        if ($rangeStart->minute % $intervalMinutes !== 0 || $rangeEnd->minute % $intervalMinutes !== 0) {
            return false;
        }

        $space = $this->spaceForRoom($room);
        $rangesQuery = BookingDetail::query()
            ->whereIn('status', $this->blockedStatuses())
            ->when($ignoreDetailId, fn ($builder) => $builder->whereKeyNot($ignoreDetailId))
            ->where(function ($query) use ($room, $space) {
                $query->where('hyve_room_id', $room->id);

                if ($room->isConferenceRoom()) {
                    $query->orWhere(function ($fallback) use ($space) {
                        $fallback->whereNull('hyve_room_id')
                            ->where('space_id', $space->id);
                    });
                }
            });
        $this->applyBookingDateOverlapConstraint($rangesQuery, $bookingDate, $bookingDate);

        $blockedRanges = $rangesQuery->get(['id', 'booking_date', 'booking_end_date', 'start_time', 'end_time'])
            ->map(function (BookingDetail $detail) use ($bookingDate, $room): array {
                if ($this->isLongStayDetail($detail) && $this->detailOccupiesDate($detail, $bookingDate)) {
                    $schedule = $this->scheduleWindowForDate($bookingDate, $room);

                    return ['start' => $schedule['start'], 'end' => $schedule['end']];
                }

                [$start, $end] = $this->dateRange($bookingDate, (string) $detail->start_time, (string) $detail->end_time);

                return ['start' => $start, 'end' => $end];
            })
            ->concat($this->calendarBlockedRangesForRoom($room, $bookingDate));

        return ! $blockedRanges->contains(fn (array $range): bool => $rangeStart->lt($range['end']) && $rangeEnd->gt($range['start']));
    }

    private function isCommonAreaTimeRangeAvailable(string $bookingDate, string $startTime, string $endTime, ?int $ignoreDetailId = null): bool
    {
        $tableRooms = HyveRoom::query()->active()->where('room_name', 'like', 'Table %')->orderBy('id')->get();
        $representative = $this->sharedTableRepresentative($tableRooms);

        if (! $representative || $this->isFullDayBlockedByCalendarEvent($representative, $bookingDate)) {
            return false;
        }

        $schedule = $this->scheduleWindowForDate($bookingDate, $representative);

        if ($schedule['closed']) {
            return false;
        }

        [$rangeStart, $rangeEnd] = $this->dateRange($bookingDate, $startTime, $endTime);
        $minimumDuration = (int) config('hyve.booking.minimum_duration_minutes', 120);
        $intervalMinutes = (int) config('hyve.booking.slot_interval_minutes', 30);

        if ($rangeStart->lt($schedule['start']) || $rangeEnd->gt($schedule['end'])) {
            return false;
        }

        if ($rangeStart->diffInMinutes($rangeEnd, true) < $minimumDuration) {
            return false;
        }

        if ($rangeStart->minute % $intervalMinutes !== 0 || $rangeEnd->minute % $intervalMinutes !== 0) {
            return false;
        }

        $details = BookingDetail::query()
            ->whereIn('status', $this->blockedStatuses())
            ->whereIn('hyve_room_id', $tableRooms->pluck('id'))
            ->when($ignoreDetailId, fn ($builder) => $builder->whereKeyNot($ignoreDetailId))
            ->where(function ($query) use ($bookingDate) {
                $this->applyBookingDateOverlapConstraint($query, $bookingDate, $bookingDate);
            })
            ->get(['id', 'booking_date', 'booking_end_date', 'start_time', 'end_time']);

        $occupancy = [];

        foreach ($details as $detail) {
            if ($this->isLongStayDetail($detail) && $this->detailOccupiesDate($detail, $bookingDate)) {
                $start = $schedule['start'];
                $end = $schedule['end'];
            } else {
                [$start, $end] = $this->dateRange($bookingDate, (string) $detail->start_time, (string) $detail->end_time);
            }

            $cursor = $start->copy();

            while ($cursor->lt($end)) {
                $key = $cursor->format('Y-m-d H:i');
                $occupancy[$key] = ($occupancy[$key] ?? 0) + 1;
                $cursor->addMinutes($intervalMinutes);
            }
        }

        foreach ($this->calendarBlockedRangesForRoom($representative, $bookingDate) as $range) {
            if ($rangeStart->lt($range['end']) && $rangeEnd->gt($range['start'])) {
                return false;
            }
        }

        $cursor = $rangeStart->copy();
        $capacity = $tableRooms->count();

        while ($cursor->lt($rangeEnd)) {
            if (($occupancy[$cursor->format('Y-m-d H:i')] ?? 0) >= $capacity) {
                return false;
            }

            $cursor->addMinutes($intervalMinutes);
        }

        return true;
    }

    private function resolveBookableRoomForSelection(HyveRoom $selectedRoom, string $bookingDate, string $startTime, string $endTime, ?int $ignoreDetailId = null): ?HyveRoom
    {
        if (! $selectedRoom->isSharedTable()) {
            return $selectedRoom;
        }

        $tableRooms = HyveRoom::query()->active()->where('room_name', 'like', 'Table %')->inRandomOrder()->get();

        foreach ($tableRooms as $tableRoom) {
            if ($this->isDirectRoomTimeRangeAvailable($tableRoom, $bookingDate, $startTime, $endTime, $ignoreDetailId)) {
                return $tableRoom;
            }
        }

        return null;
    }
}
