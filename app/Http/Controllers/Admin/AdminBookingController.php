<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\BookingApprovedMail;
use App\Mail\BookingPaymentReceiptMail;
use App\Mail\BookingRejectedMail;
use App\Mail\BookingRescheduledMail;
use App\Models\BookingActivity;
use App\Models\BookingDetail;
use App\Models\BookingHeader;
use App\Models\BookingPayment;
use App\Models\HyveCalendarEvent;
use App\Models\HyveRoom;
use App\Models\HyveScheduleOverride;
use App\Services\AdminBookingRescheduleService;
use App\Services\BookingApprovalTextService;
use App\Services\BookingProgressSyncService;
use App\Services\BookingRescheduledTextService;
use App\Services\BookingWifiVoucherService;
use App\Services\HyveDiscountService;
use App\Services\HyveOperatingScheduleService;
use App\Support\HyvePricing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AdminBookingController extends Controller
{
    private const EXTENSION_MINUTE_OPTIONS = [30, 60, 90, 120, 180, 240, 360, 480];

    public function __construct(
        private readonly BookingWifiVoucherService $wifiVoucherService,
        private readonly HyvePricing $pricing,
        private readonly BookingProgressSyncService $progressSync,
        private readonly AdminBookingRescheduleService $rescheduleService,
        private readonly HyveDiscountService $discounts,
        private readonly HyveOperatingScheduleService $operatingSchedule,
    ) {}

    public function index(Request $request): View
    {
        $this->syncDueBookingsProgress();
        $this->markOnlineBookingActivitiesRead();

        $filters = $this->bookingFilters($request);
        $bookings = $this->bookingListingPaginator($request, $filters);
        $initialNotifications = $this->initialBookingNotifications();

        return view('admin.bookings.index', [
            'meta' => [
                'title' => 'Bookings | HYVE Admin',
                'description' => 'Review booking submissions, approve reservations, and reject invalid requests.',
            ],
            'adminUser' => $request->user(),
            'bookings' => $bookings,
            'activities' => $initialNotifications['activities'],
            'activityUnreadCount' => $initialNotifications['unread_count'],
            'filters' => $filters,
        ]);
    }

    public function bookingsFeed(Request $request): JsonResponse
    {
        // Keep booking progress current during the browser's live polling too.
        // This lets local development auto-start/end bookings without requiring
        // a manual page refresh while the Admin Bookings page is open.
        $this->syncDueBookingsProgress();

        $filters = $this->bookingFilters($request);
        $bookings = $this->bookingListingPaginator($request, $filters);

        return response()->json([
            'total' => $bookings->total(),
            'current_page' => $bookings->currentPage(),
            'last_page' => $bookings->lastPage(),
            'bookings' => $bookings->items(),
        ]);
    }

    public function onlineBookingUnread(): JsonResponse
    {
        if (! Schema::hasTable('booking_activities')) {
            return response()->json([
                'unread_total' => 0,
                'latest_booking' => null,
            ]);
        }

        $query = $this->unreadOnlineBookingActivitiesQuery();
        $latest = (clone $query)
            ->with('bookingHeader:id,reference_no,customer_name')
            ->latest('id')
            ->first();

        return response()->json([
            'unread_total' => (clone $query)->distinct()->count('booking_header_id'),
            'latest_booking' => $latest ? [
                'id' => $latest->getKey(),
                'reference_no' => (string) ($latest->bookingHeader?->reference_no ?: $latest->reference_no),
                'customer_name' => (string) ($latest->bookingHeader?->customer_name ?: $latest->customer_name ?: 'Customer'),
            ] : null,
        ]);
    }

    public function summary(BookingHeader $bookingHeader): JsonResponse
    {
        $bookingHeader->load(['details.hyveRoom', 'details.space', 'user', 'wifiVoucher']);

        return response()->json([
            'booking' => $this->bookingRowPayload($bookingHeader),
        ]);
    }

    public function proof(BookingHeader $bookingHeader)
    {
        $path = (string) ($bookingHeader->payment_proof_path ?? '');

        abort_if($path === '', 404);
        abort_unless(Storage::disk('public')->exists($path), 404);

        $filePath = Storage::disk('public')->path($path);
        $mimeType = Storage::disk('public')->mimeType($path) ?: 'application/octet-stream';

        return response()->file($filePath, [
            'Content-Type' => $mimeType,
            'Content-Disposition' => 'inline; filename="'.basename($path).'"',
        ]);
    }

    public function studentIdProof(BookingDetail $bookingDetail)
    {
        $path = (string) ($bookingDetail->student_id_proof_path ?? '');

        abort_if($path === '', 404);
        abort_unless(Storage::disk('public')->exists($path), 404);

        return response()->file(Storage::disk('public')->path($path), [
            'Content-Type' => Storage::disk('public')->mimeType($path) ?: 'application/octet-stream',
            'Content-Disposition' => 'inline; filename="'.basename($path).'"',
        ]);
    }

    public function reschedule(Request $request, BookingDetail $bookingDetail): View|RedirectResponse
    {
        $bookingDetail->loadMissing(['bookingHeader.payments', 'hyveRoom', 'space']);

        if (! $this->rescheduleService->canReschedule($bookingDetail)) {
            return redirect()->route('admin.bookings.index')
                ->with('admin_success', 'This booking can no longer be rescheduled because its scheduled start has arrived or it has already started.');
        }

        $rooms = $this->rescheduleService->selectableRooms();

        return view('admin.bookings.reschedule', [
            'meta' => [
                'title' => 'Reschedule Booking | HYVE Admin',
                'description' => 'Move a future booking while preserving its reference and payment history.',
            ],
            'adminUser' => $request->user(),
            'bookingDetail' => $bookingDetail,
            'bookingHeader' => $bookingDetail->bookingHeader,
            'displayRooms' => $rooms,
            'selectedRoomId' => $this->rescheduleService->selectedRoomId($bookingDetail, $rooms),
            'isLongStay' => $this->rescheduleService->isLongStay($bookingDetail),
            'slotsUrl' => route('admin.booking-details.reschedule.slots', $bookingDetail),
            'previewUrl' => route('admin.booking-details.reschedule.preview', $bookingDetail),
        ]);
    }

    public function rescheduleSlots(Request $request, BookingDetail $bookingDetail): JsonResponse
    {
        $validated = $request->validate([
            'hyve_room_id' => ['required', 'integer', Rule::exists('hyve_rooms', 'id')->where(fn ($query) => $query->where('status', 0))],
            'booking_date' => ['required', 'date', 'after_or_equal:today'],
            'start_time' => ['nullable', 'regex:/^(?:[01]\d|2[0-3]):[0-5]\d$/'],
        ]);

        return response()->json($this->rescheduleService->availableSlots(
            $bookingDetail,
            (int) $validated['hyve_room_id'],
            (string) $validated['booking_date'],
            filled($validated['start_time'] ?? null) ? (string) $validated['start_time'] : null,
        ));
    }

    public function reschedulePreview(Request $request, BookingDetail $bookingDetail): JsonResponse
    {
        $data = $this->validatedRescheduleData($request, $bookingDetail);
        $preview = $this->rescheduleService->preview($bookingDetail, $data);

        return response()->json([
            'available' => true,
            'rate_name' => $preview['quote']['rate_name'] ?? 'Updated rate',
            'old_line_total' => $preview['old_line_total'],
            'new_line_total' => $preview['new_line_total'],
            'price_difference' => $preview['price_difference'],
            'new_total' => $preview['new_effective_total'],
            'approved_total' => $preview['approved_total'],
            'new_balance' => $preview['new_balance'],
            'overpayment' => $preview['overpayment'],
            'requires_price_confirmation' => $preview['requires_price_confirmation'],
        ]);
    }

    public function updateReschedule(Request $request, BookingDetail $bookingDetail): RedirectResponse
    {
        $data = $this->validatedRescheduleData($request, $bookingDetail);
        $result = $this->rescheduleService->reschedule($bookingDetail, $data, $request->user()?->getKey());
        /** @var BookingHeader $header */
        $header = $result['header'];

        if ((string) $header->status === 'confirmed') {
            $this->wifiVoucherService->ensureVoucherForBooking($header);
        }

        $this->sendRescheduleNotifications($result);

        $message = 'Booking rescheduled successfully. The reference and approved payments were preserved.';

        if ((float) $result['overpayment'] > 0) {
            $message .= ' Review the Php '.number_format((float) $result['overpayment'], 2).' excess payment with the customer.';
        }

        return redirect()->route('admin.bookings.index')->with('admin_success', $message);
    }

    public function approve(Request $request, BookingHeader $bookingHeader): RedirectResponse
    {
        $bookingHeader->update([
            'status' => 'confirmed',
            'payment_status' => $this->resolvedBookingApprovalPaymentStatus($bookingHeader),
        ]);

        $bookingHeader->details()
            ->where('status', '!=', 'cancelled')
            ->update(['status' => 'confirmed']);

        $this->recordActivity(
            $bookingHeader,
            null,
            'booking_approved',
            'Booking approved',
            'Approved booking for '.$bookingHeader->customer_name.'.'
        );

        $this->wifiVoucherService->ensureVoucherForBooking($bookingHeader->fresh(['details']));
        $this->sendApprovalNotifications($bookingHeader->fresh(['details.hyveRoom', 'details.space']));

        return back()->with('admin_success', 'Booking approved successfully.');
    }

    public function approveDetail(Request $request, BookingDetail $bookingDetail): JsonResponse|RedirectResponse
    {
        $bookingDetail->update([
            'status' => BookingDetail::STATUS_CONFIRMED,
            'progress_status' => BookingDetail::PROGRESS_SCHEDULED,
        ]);

        $header = $bookingDetail->bookingHeader()->with('details')->firstOrFail();
        $this->syncHeaderStatus($header);
        $this->syncWifiVoucher($header->fresh(['details', 'wifiVoucher']));
        $bookingDetail = $bookingDetail->fresh(['hyveRoom', 'space']);

        $this->recordActivity(
            $header,
            $bookingDetail,
            'booking_line_approved',
            'Booked line approved',
            'Approved '.$this->activityRoomName($bookingDetail).' for '.$header->customer_name.'.'
        );

        $this->sendApprovalNotifications(
            $header->fresh(['details.hyveRoom', 'details.space']),
            $bookingDetail->fresh(['hyveRoom', 'space'])
        );

        if ($request->expectsJson()) {
            $freshHeader = $header->fresh(['details.hyveRoom', 'details.space', 'payments', 'user', 'wifiVoucher']);

            return response()->json([
                'message' => 'Booked line approved successfully.',
                'booking' => $this->bookingRowPayload($freshHeader),
                'detail' => [
                    'id' => $bookingDetail->getKey(),
                    'status' => 'Approved',
                    'status_class' => 'admin-bookings-badge--confirmed',
                    'can_review' => false,
                    ...$this->detailProgressPayload($bookingDetail->fresh()),
                ],
                'header' => [
                    'status' => ucfirst((string) $header->status),
                    'status_class' => $this->headerStatusClass((string) $header->status),
                    'payment_status' => $this->paymentStatusLabel((string) ($header->payment_status ?? 'pending_verification')),
                    'payment_status_key' => (string) ($header->payment_status ?? 'pending_verification'),
                    'payment_status_class' => $this->paymentStatusClass((string) ($header->payment_status ?? 'pending_verification')),
                ],
            ]);
        }

        return back()->with('admin_success', 'Booked line approved successfully.');
    }

    public function reject(Request $request, BookingHeader $bookingHeader): RedirectResponse
    {
        $bookingHeader->update([
            'status' => 'cancelled',
            'payment_status' => 'rejected',
        ]);

        $bookingHeader->details()->update(['status' => 'cancelled']);

        $this->recordActivity(
            $bookingHeader,
            null,
            'booking_rejected',
            'Booking rejected',
            'Rejected booking for '.$bookingHeader->customer_name.'.'
        );

        $this->wifiVoucherService->revokeVoucherForBooking($bookingHeader->fresh('wifiVoucher'));
        $this->sendRejectionNotifications($bookingHeader->fresh(['details.hyveRoom', 'details.space']));

        return back()->with('admin_success', 'Booking rejected and marked as cancelled.');
    }

    public function rejectDetail(Request $request, BookingDetail $bookingDetail): JsonResponse|RedirectResponse
    {
        $bookingDetail->update([
            'status' => BookingDetail::STATUS_CANCELLED,
            'progress_status' => BookingDetail::PROGRESS_SCHEDULED,
            'actual_start_at' => null,
            'actual_end_at' => null,
        ]);

        $header = $bookingDetail->bookingHeader()->with('details')->firstOrFail();
        $this->syncHeaderStatus($header);
        $this->syncWifiVoucher($header->fresh(['details', 'wifiVoucher']));
        $bookingDetail = $bookingDetail->fresh(['hyveRoom', 'space']);

        $this->recordActivity(
            $header,
            $bookingDetail,
            'booking_line_rejected',
            'Booked line rejected',
            'Rejected '.$this->activityRoomName($bookingDetail).' for '.$header->customer_name.'.'
        );

        $this->sendRejectionNotifications(
            $header->fresh(['details.hyveRoom', 'details.space']),
            $bookingDetail->fresh(['hyveRoom', 'space'])
        );

        if ($request->expectsJson()) {
            $freshHeader = $header->fresh(['details.hyveRoom', 'details.space', 'payments', 'user', 'wifiVoucher']);

            return response()->json([
                'message' => 'Booked line rejected successfully.',
                'booking' => $this->bookingRowPayload($freshHeader),
                'detail' => [
                    'id' => $bookingDetail->getKey(),
                    'status' => 'Rejected',
                    'status_class' => 'admin-bookings-badge--rejected',
                    'can_review' => false,
                    ...$this->detailProgressPayload($bookingDetail->fresh()),
                ],
                'header' => [
                    'status' => ucfirst((string) $header->status),
                    'status_class' => $this->headerStatusClass((string) $header->status),
                    'payment_status' => $this->paymentStatusLabel((string) ($header->payment_status ?? 'pending_verification')),
                    'payment_status_key' => (string) ($header->payment_status ?? 'pending_verification'),
                    'payment_status_class' => $this->paymentStatusClass((string) ($header->payment_status ?? 'pending_verification')),
                ],
            ]);
        }

        return back()->with('admin_success', 'Booked line rejected successfully.');
    }

    public function startDetail(Request $request, BookingDetail $bookingDetail): JsonResponse|RedirectResponse
    {
        if (! $this->canStartDetail($bookingDetail)) {
            return $this->detailActionErrorResponse($request, 'This booked line is not ready to start yet.');
        }

        $bookingDetail->update([
            'progress_status' => BookingDetail::PROGRESS_IN_PROGRESS,
            'actual_start_at' => now(),
            'actual_end_at' => null,
        ]);

        $bookingDetail = $bookingDetail->fresh(['hyveRoom', 'space']);
        $header = $bookingDetail->bookingHeader;

        if ($header) {
            $this->recordActivity(
                $header,
                $bookingDetail,
                'booking_started',
                'Booking started',
                'Started '.$this->activityRoomName($bookingDetail).' for '.$header->customer_name.'.'
            );
        }

        if ($request->expectsJson()) {
            $freshHeader = $header?->fresh(['details.hyveRoom', 'details.space', 'payments', 'user', 'wifiVoucher']);

            return response()->json([
                'message' => 'Booked line started successfully.',
                'booking' => $freshHeader ? $this->bookingRowPayload($freshHeader) : null,
                'detail' => [
                    'id' => $bookingDetail->getKey(),
                    ...$this->detailProgressPayload($bookingDetail->fresh()),
                ],
            ]);
        }

        return back()->with('admin_success', 'Booked line started successfully.');
    }

    public function endDetail(Request $request, BookingDetail $bookingDetail): JsonResponse|RedirectResponse
    {
        if ($bookingDetail->is_open_time) {
            if (! $this->canCheckoutOpenTimeDetail($bookingDetail)) {
                return $this->detailActionErrorResponse($request, 'This Open Time session has already been checked out.');
            }

            return $this->endOpenTimeDetail($request, $bookingDetail);
        }

        if (! $this->canEndDetail($bookingDetail)) {
            return $this->detailActionErrorResponse($request, 'This booked line cannot be ended yet.');
        }

        $endedAt = now();
        $sessionStartedAt = $this->timedSessionDetails($bookingDetail)
            ->map(fn (BookingDetail $sessionDetail) => $sessionDetail->actual_start_at)
            ->filter()
            ->sortBy(fn (Carbon $value) => $value->timestamp)
            ->first();

        $this->timedSessionDetails($bookingDetail)
            ->where('status', BookingDetail::STATUS_CONFIRMED)
            ->each(function (BookingDetail $sessionDetail) use ($endedAt, $sessionStartedAt): void {
                $sessionDetail->update([
                    'progress_status' => BookingDetail::PROGRESS_COMPLETED,
                    'actual_start_at' => $sessionDetail->actual_start_at ?? $sessionStartedAt ?? $endedAt,
                    'actual_end_at' => $endedAt,
                ]);
            });

        $bookingDetail = $bookingDetail->fresh(['hyveRoom', 'space']);
        $header = $bookingDetail->bookingHeader;

        if ($header) {
            $this->recordActivity(
                $header,
                $bookingDetail,
                'booking_completed',
                'Booking ended',
                'Ended '.$this->activityRoomName($bookingDetail).' for '.$header->customer_name.'.'
            );
        }

        if ($request->expectsJson()) {
            $freshHeader = $header?->fresh(['details.hyveRoom', 'details.space', 'payments', 'user', 'wifiVoucher']);

            return response()->json([
                'message' => 'Booked line ended successfully.',
                'booking' => $freshHeader ? $this->bookingRowPayload($freshHeader) : null,
                'detail' => [
                    'id' => $bookingDetail->getKey(),
                    ...$this->detailProgressPayload($bookingDetail->fresh()),
                ],
            ]);
        }

        return back()->with('admin_success', 'Booked line ended successfully.');
    }

    public function openTimeCheckoutPreview(BookingDetail $bookingDetail): JsonResponse
    {
        $bookingDetail->loadMissing(['bookingHeader.payments', 'hyveRoom', 'space']);

        if (! $this->canCheckoutOpenTimeDetail($bookingDetail)) {
            return response()->json([
                'message' => 'This Open Time session cannot be checked out right now.',
            ], 422);
        }

        $endedAt = $bookingDetail->actual_end_at ?: now();
        $calculation = $this->openTimeCheckoutCalculation($bookingDetail, $bookingDetail->bookingHeader, $endedAt);

        return response()->json($this->openTimeCheckoutPayload($bookingDetail, $calculation));
    }

    private function endOpenTimeDetail(Request $request, BookingDetail $bookingDetail): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'payment_method' => ['required', Rule::in(['cash', 'gcash', 'bank_transfer'])],
            'previewed_amount_due' => ['required', 'numeric', 'min:0'],
            'payment_notes' => [
                Rule::requiredIf(fn (): bool => in_array((string) $request->input('payment_method'), ['gcash', 'bank_transfer'], true)),
                'nullable',
                'string',
                'max:1000',
            ],
        ]);
        $receiptHeader = null;

        DB::transaction(function () use ($request, $bookingDetail, $validated, &$receiptHeader): void {
            $detail = BookingDetail::query()->lockForUpdate()->findOrFail($bookingDetail->getKey());
            $header = BookingHeader::query()->lockForUpdate()->findOrFail($detail->booking_header_id);
            $detail->loadMissing(['hyveRoom', 'space']);

            if (! $this->canCheckoutOpenTimeDetail($detail)) {
                throw ValidationException::withMessages([
                    'payment_method' => 'This Open Time session has already been checked out.',
                ]);
            }

            $endedAt = $detail->actual_end_at ?: now();
            $calculation = $this->openTimeCheckoutCalculation($detail, $header, $endedAt);
            $quote = $calculation['quote'];

            $detail->update([
                'charge_period' => $quote['charge_period'],
                'duration_hours' => round($calculation['elapsed_minutes'] / 60, 2),
                'billed_hours' => round($calculation['billed_minutes'] / 60, 2),
                'rate_name' => $quote['rate_name'].' - Open Time',
                'rate_amount' => $quote['succeeding_hour_rate'],
                'subtotal' => round((float) $quote['total_amount'], 2),
                'progress_status' => BookingDetail::PROGRESS_COMPLETED,
                'actual_start_at' => $calculation['started_at'],
                'actual_end_at' => $endedAt,
            ]);

            $grossTotal = $calculation['gross_total'];
            $discount = $calculation['discount'];
            $finalTotal = $calculation['final_total'];
            $approvedBefore = $calculation['approved_before'];
            $amountDue = $calculation['amount_due'];

            if (abs($amountDue - (float) $validated['previewed_amount_due']) > 0.009) {
                throw ValidationException::withMessages([
                    'previewed_amount_due' => 'The live bill changed while this checkout was open. Close and reopen End & Checkout to review the updated amount.',
                ]);
            }

            if ($amountDue > 0) {
                BookingPayment::query()->create([
                    'booking_header_id' => $header->getKey(),
                    'booking_detail_id' => $detail->getKey(),
                    'user_id' => $header->user_id,
                    'payment_type' => BookingPayment::TYPE_BALANCE,
                    'amount' => $amountDue,
                    'payment_method' => $validated['payment_method'],
                    'status' => BookingPayment::STATUS_APPROVED,
                    'notes' => trim((string) ($validated['payment_notes'] ?? ''))
                        ?: 'Open Time payment collected at checkout.',
                    'paid_at' => $endedAt,
                    'verified_at' => $endedAt,
                    'verified_by' => $request->user()?->getKey(),
                ]);
            }

            $header->update([
                'payment_method' => $validated['payment_method'],
                'payment_status' => 'paid',
                'total_amount' => $grossTotal,
                'discount_amount' => $discount['discount_amount'],
                'discounted_total_amount' => $finalTotal,
                'downpayment_amount' => round($approvedBefore + $amountDue, 2),
                'balance_amount' => 0,
            ]);

            $this->recordActivity(
                $header,
                $detail,
                'open_time_checked_out',
                'Open Time checked out',
                'Ended '.$this->activityRoomName($detail).' and collected Php '.number_format($amountDue, 2)
                    .' via '.ucfirst(str_replace('_', ' ', (string) $validated['payment_method'])).'.'
            );

            $this->syncWifiVoucher($header->fresh(['details', 'wifiVoucher']));
            $receiptHeader = $header->fresh(['details.hyveRoom', 'details.space', 'payments']);
        });

        $this->sendPaymentReceiptEmail($receiptHeader);

        $bookingDetail = $bookingDetail->fresh(['hyveRoom', 'space']);
        $header = $bookingDetail->bookingHeader;

        if ($request->expectsJson()) {
            $freshHeader = $header?->fresh(['details.hyveRoom', 'details.space', 'payments', 'user', 'wifiVoucher']);

            return response()->json([
                'message' => 'Open Time session ended and payment recorded successfully.',
                'booking' => $freshHeader ? $this->bookingRowPayload($freshHeader) : null,
                'detail' => [
                    'id' => $bookingDetail->getKey(),
                    ...$this->detailProgressPayload($bookingDetail),
                ],
            ]);
        }

        return back()->with('admin_success', 'Open Time session ended and payment recorded successfully.');
    }

    public function extensionOptions(BookingDetail $bookingDetail): JsonResponse
    {
        $bookingDetail->loadMissing(['bookingHeader.payments', 'hyveRoom', 'space']);

        if (! $this->canExtendDetail($bookingDetail)) {
            return response()->json([
                'message' => 'This booked line cannot be extended right now.',
            ], 422);
        }

        $currentEnd = $this->scheduledDateTime($bookingDetail, (string) $bookingDetail->end_time, true);
        $header = $bookingDetail->bookingHeader;
        $options = [];
        $stoppedBy = null;

        foreach (self::EXTENSION_MINUTE_OPTIONS as $minutes) {
            $candidateEnd = $currentEnd->copy()->addMinutes($minutes);
            $conflict = $this->extensionConflictReason($bookingDetail, $currentEnd, $candidateEnd);

            if ($conflict !== null) {
                $stoppedBy = $conflict;
                break;
            }

            $quote = $this->pricing->quoteExtensionForRoom(
                $bookingDetail->hyveRoom,
                $currentEnd->toDateString(),
                $currentEnd->format('H:i'),
                $candidateEnd->format('H:i'),
            );

            if (! $quote) {
                break;
            }

            $amount = round((float) ($quote['total_amount'] ?? 0), 2);
            $financials = $this->extensionFinancialPreview($header, $bookingDetail, $quote, $currentEnd, $amount);
            $options[] = [
                'duration_minutes' => $minutes,
                'duration_label' => $this->extensionDurationLabel($minutes),
                'end_at' => $candidateEnd->format('Y-m-d H:i'),
                'end_label' => $candidateEnd->format('M j, Y g:i A'),
                'amount' => $amount,
                'amount_label' => 'Php '.number_format($amount, 2),
                'new_total' => $financials['new_total'],
                'new_total_label' => 'Php '.number_format($financials['new_total'], 2),
                'new_balance' => $financials['new_balance'],
                'new_balance_label' => 'Php '.number_format($financials['new_balance'], 2),
            ];
        }

        return response()->json([
            'room' => $this->activityRoomName($bookingDetail),
            'current_end' => $currentEnd->format('M j, Y g:i A'),
            'options' => $options,
            'availability_note' => $stoppedBy
                ? 'Later choices are unavailable: '.$stoppedBy
                : 'Only conflict-free choices within the maximum extension window are shown.',
        ]);
    }

    public function extendDetail(Request $request, BookingDetail $bookingDetail): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'extension_end_at' => ['required', 'date_format:Y-m-d H:i'],
        ]);

        $freshHeader = DB::transaction(function () use ($bookingDetail, $validated): BookingHeader {
            $lockedDetail = BookingDetail::query()
                ->whereKey($bookingDetail->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $lockedDetail->loadMissing(['bookingHeader.payments', 'hyveRoom', 'space']);

            if (! $this->canExtendDetail($lockedDetail)) {
                throw ValidationException::withMessages([
                    'extension_end_at' => 'This booked line can no longer be extended.',
                ]);
            }

            BookingHeader::query()->whereKey($lockedDetail->booking_header_id)->lockForUpdate()->firstOrFail();
            HyveRoom::query()->whereKey($lockedDetail->hyve_room_id)->lockForUpdate()->firstOrFail();

            $extensionStart = $this->scheduledDateTime($lockedDetail, (string) $lockedDetail->end_time, true);
            $extensionEnd = Carbon::createFromFormat('Y-m-d H:i', (string) $validated['extension_end_at']);
            $extensionMinutes = (int) $extensionStart->diffInMinutes($extensionEnd, false);

            if (! in_array($extensionMinutes, self::EXTENSION_MINUTE_OPTIONS, true)) {
                throw ValidationException::withMessages([
                    'extension_end_at' => 'Select one of the available extension choices.',
                ]);
            }

            $conflict = $this->extensionConflictReason($lockedDetail, $extensionStart, $extensionEnd);

            if ($conflict !== null) {
                throw ValidationException::withMessages([
                    'extension_end_at' => 'This extension is no longer available: '.$conflict,
                ]);
            }

            $quote = $this->pricing->quoteExtensionForRoom(
                $lockedDetail->hyveRoom,
                $extensionStart->toDateString(),
                $extensionStart->format('H:i'),
                $extensionEnd->format('H:i'),
            );

            if (! $quote) {
                throw ValidationException::withMessages([
                    'extension_end_at' => 'Unable to compute the extension amount for this room right now.',
                ]);
            }

            $header = $lockedDetail->bookingHeader;
            $isRetrospectiveExtension = $lockedDetail->actual_end_at !== null;
            $extendedDetail = $header->details()->create([
                'space_id' => $lockedDetail->space_id,
                'hyve_room_id' => $lockedDetail->hyve_room_id,
                'booking_date' => $extensionStart->toDateString(),
                'booking_end_date' => $extensionEnd->toDateString(),
                'start_time' => $extensionStart->format('H:i:s'),
                'end_time' => $extensionEnd->format('H:i:s'),
                'charge_period' => (string) ($quote['charge_period'] ?? 'day'),
                'duration_hours' => (float) ($quote['duration_hours'] ?? 0),
                'billed_hours' => (float) ($quote['billed_hours'] ?? 0),
                'guests' => (int) ($lockedDetail->guests ?? 1),
                'rate_name' => (string) ($quote['rate_name'] ?? 'Extension'),
                'rate_amount' => (float) ($quote['succeeding_hour_rate'] ?? $quote['total_amount'] ?? 0),
                'subtotal' => (float) ($quote['total_amount'] ?? 0),
                'status' => BookingDetail::STATUS_CONFIRMED,
                'progress_status' => $isRetrospectiveExtension
                    ? BookingDetail::PROGRESS_COMPLETED
                    : BookingDetail::PROGRESS_SCHEDULED,
                'actual_start_at' => $isRetrospectiveExtension ? $extensionStart : null,
                'actual_end_at' => $isRetrospectiveExtension ? $extensionEnd : null,
            ]);

            $freshHeader = $header->fresh(['details.hyveRoom', 'details.space', 'payments', 'user', 'wifiVoucher']);
            $this->syncHeaderStatus($freshHeader->fresh(['details']));
            $this->syncHeaderFinancialSnapshot($freshHeader->fresh(['details', 'payments']));
            $this->syncWifiVoucher($freshHeader->fresh(['details', 'wifiVoucher']));
            $freshHeader = $freshHeader->fresh(['details.hyveRoom', 'details.space', 'payments', 'user', 'wifiVoucher']);

            $this->recordActivity(
                $freshHeader,
                $extendedDetail->fresh(['hyveRoom', 'space']),
                'booking_line_extended',
                'Booking extended',
                'Extended '.$this->activityRoomName($extendedDetail).' for '.$freshHeader->customer_name.' until '.$this->displayDateTime($extensionEnd).'.'
            );

            return $freshHeader;
        }, 3);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Booking extended successfully.',
                'booking' => $this->bookingRowPayload($freshHeader),
            ]);
        }

        return back()->with('admin_success', 'Booking extended successfully.');
    }

    public function markNotificationsRead(Request $request): JsonResponse
    {
        if (! Schema::hasTable('booking_activities')) {
            return response()->json([
                'message' => 'Booking notifications marked as read.',
            ]);
        }

        BookingActivity::query()
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json([
            'message' => 'Booking notifications marked as read.',
        ]);
    }

    public function notificationsFeed(Request $request): JsonResponse
    {
        return response()->json($this->bookingNotificationPayload());
    }

    private function syncHeaderStatus(BookingHeader $bookingHeader): void
    {
        $details = $bookingHeader->details;
        $statuses = $details->pluck('status')->map(fn ($status): string => (string) $status)->values();

        if ($statuses->isEmpty()) {
            return;
        }

        $headerStatus = BookingHeader::STATUS_PENDING;

        if ($statuses->every(fn (string $status): bool => $status === 'cancelled')) {
            $headerStatus = 'cancelled';
        } elseif ($statuses->every(fn (string $status): bool => $status === 'confirmed')) {
            $headerStatus = 'confirmed';
        }

        $paymentStatus = (string) ($bookingHeader->payment_status ?? 'pending_verification');

        if ($headerStatus === 'cancelled') {
            $paymentStatus = 'rejected';
        } elseif ((float) ($bookingHeader->balance_amount ?? 0) <= 0) {
            $paymentStatus = 'paid';
        } elseif ((float) ($bookingHeader->downpayment_amount ?? 0) > 0) {
            $paymentStatus = 'partially_paid';
        } elseif ($paymentStatus === 'rejected') {
            $paymentStatus = 'pending_verification';
        }

        $bookingHeader->update([
            'status' => $headerStatus,
            'payment_status' => $paymentStatus,
        ]);
    }

    private function paymentStatusClass(string $paymentStatus): string
    {
        return match ($paymentStatus) {
            'paid' => 'admin-bookings-badge--paid',
            'rejected' => 'admin-bookings-badge--rejected',
            'partially_paid' => 'admin-bookings-badge--partial',
            default => 'admin-bookings-badge--pending',
        };
    }

    private function headerStatusClass(string $status): string
    {
        return match ($status) {
            'confirmed' => 'admin-bookings-badge--confirmed',
            'cancelled' => 'admin-bookings-badge--rejected',
            default => 'admin-bookings-badge--pending',
        };
    }

    private function detailActionErrorResponse(Request $request, string $message): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
            ], 422);
        }

        return back()->with('admin_error', $message);
    }

    private function scheduledDateTime(BookingDetail $detail, string $time, bool $isEnd = false): Carbon
    {
        $date = $isEnd && $detail->booking_end_date
            ? $detail->booking_end_date
            : $detail->booking_date;
        $result = Carbon::parse(optional($date)->format('Y-m-d').' '.$time);

        if ($isEnd) {
            $start = Carbon::parse(optional($detail->booking_date)->format('Y-m-d').' '.$detail->start_time);

            if ($result->lte($start)) {
                $result->addDay();
            }
        }

        return $result;
    }

    private function isLongStayDetail(BookingDetail $detail): bool
    {
        return in_array((string) $detail->charge_period, ['daily', 'weekly', 'monthly'], true);
    }

    private function detailDateLabel(BookingDetail $detail): string
    {
        $startDate = $detail->booking_date;
        $endDate = $detail->booking_end_date ?: $startDate;

        if (! $startDate) {
            return '--';
        }

        if ($endDate && $endDate->ne($startDate)) {
            return $startDate->format('F j, Y').' - '.$endDate->format('F j, Y');
        }

        return $startDate->format('F j, Y');
    }

    private function detailTimeLabel(BookingDetail $detail): string
    {
        if ($this->isLongStayDetail($detail)) {
            return match ((string) $detail->charge_period) {
                'monthly' => 'Monthly stay',
                'weekly' => 'Weekly stay',
                'daily' => 'Daily stay',
                default => 'Long stay booking',
            };
        }

        $nextDay = $detail->booking_end_date
            && $detail->booking_date
            && $detail->booking_end_date->ne($detail->booking_date);

        return Carbon::parse((string) $detail->start_time)->format('g:i A')
            .' - '
            .Carbon::parse((string) $detail->end_time)->format('g:i A')
            .($nextDay ? ' next day' : '');
    }

    private function detailScheduledStartLabel(BookingDetail $detail, Carbon $scheduledStart): string
    {
        if ($this->isLongStayDetail($detail)) {
            return optional($detail->booking_date)?->format('F j, Y') ?? '--';
        }

        return $scheduledStart->format('F j, Y g:i A');
    }

    private function detailScheduledEndLabel(BookingDetail $detail, Carbon $scheduledEnd): string
    {
        if ($this->isLongStayDetail($detail)) {
            return optional($detail->booking_end_date ?: $detail->booking_date)?->format('F j, Y') ?? '--';
        }

        return $scheduledEnd->format('F j, Y g:i A');
    }

    private function canExtendDetail(BookingDetail $detail): bool
    {
        if (! $this->isLatestTimedSessionDetail($detail)) {
            return false;
        }

        $scheduledEnd = $this->scheduledDateTime($detail, (string) $detail->end_time, true);
        $extensionDeadline = $detail->actual_end_at
            ? $detail->actual_end_at->copy()->addHours(
                max(0, (int) config('hyve.booking.ended_extension_window_hours', 24))
            )
            : $scheduledEnd->copy()->addMinutes(
                max(0, (int) config('hyve.booking.extension_grace_minutes', 30))
            );

        return (string) $detail->status === BookingDetail::STATUS_CONFIRMED
            && ! in_array((string) $detail->charge_period, ['weekly', 'monthly'], true)
            && ! $detail->is_open_time
            && $detail->hyve_room_id !== null
            && $detail->bookingHeader !== null
            && now()->lte($extensionDeadline);
    }

    private function canCheckoutOpenTimeDetail(BookingDetail $detail): bool
    {
        if (! $detail->is_open_time
            || (string) $detail->status !== BookingDetail::STATUS_CONFIRMED
            || ! $detail->actual_start_at
            || ! $detail->hyve_room_id
            || ! $detail->booking_header_id) {
            return false;
        }

        return ! BookingActivity::query()
            ->where('booking_detail_id', $detail->getKey())
            ->where('event_key', 'open_time_checked_out')
            ->exists();
    }

    private function canStartDetail(BookingDetail $detail): bool
    {
        if (! $this->isFirstTimedSessionDetail($detail) || $this->sessionHasStarted($detail)) {
            return false;
        }

        return (string) $detail->status === BookingDetail::STATUS_CONFIRMED
            && ! $detail->actual_start_at
            && ! $detail->actual_end_at;
    }

    private function canEndDetail(BookingDetail $detail): bool
    {
        if (! $this->isLatestTimedSessionDetail($detail) || ! $this->sessionHasStarted($detail)) {
            return false;
        }

        return (string) $detail->status === BookingDetail::STATUS_CONFIRMED
            && (string) $detail->progress_status !== BookingDetail::PROGRESS_COMPLETED
            && ! $detail->actual_end_at;
    }

    private function detailProgressPayload(BookingDetail $detail): array
    {
        $scheduledStart = $this->scheduledDateTime($detail, (string) $detail->start_time);
        $scheduledEnd = $this->scheduledDateTime($detail, (string) $detail->end_time, true);
        $progressMeta = $this->progressMeta($detail, $scheduledStart, $scheduledEnd);

        return [
            'progress' => $progressMeta['label'],
            'progress_class' => $progressMeta['class'],
            'progress_key' => $progressMeta['key'],
            'actual_start' => optional($detail->actual_start_at)?->format('F j, Y g:i A') ?? '--',
            'actual_end' => optional($detail->actual_end_at)?->format('F j, Y g:i A') ?? '--',
            'can_start' => $this->canStartDetail($detail),
            'can_end' => $this->canEndDetail($detail),
        ];
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function timedRangeForDate(string $bookingDate, string $startTime, string $endTime): array
    {
        $start = Carbon::parse($bookingDate.' '.$startTime);
        $end = Carbon::parse($bookingDate.' '.$endTime);

        if ($end->lessThanOrEqualTo($start)) {
            $end->addDay();
        }

        return [$start, $end];
    }

    /**
     * @return array{0: Carbon, 1: Carbon}|null
     */
    private function detailTimedRange(BookingDetail $detail): ?array
    {
        $bookingDate = optional($detail->booking_date)->toDateString();

        if (! $bookingDate || ! $detail->start_time || ! $detail->end_time) {
            return null;
        }

        return $this->timedRangeForDate(
            $bookingDate,
            substr((string) $detail->start_time, 0, 5),
            substr((string) $detail->end_time, 0, 5),
        );
    }

    /**
     * @return Collection<int, BookingDetail>
     */
    private function timedSessionDetails(BookingDetail $detail)
    {
        $bookingDate = optional($detail->booking_date)->toDateString();

        if (! $detail->booking_header_id
            || ! $detail->hyve_room_id
            || ! $bookingDate
            || in_array((string) $detail->charge_period, ['weekly', 'monthly'], true)) {
            return collect([$detail]);
        }

        return BookingDetail::query()
            ->where('booking_header_id', $detail->booking_header_id)
            ->where('hyve_room_id', $detail->hyve_room_id)
            ->whereDate('booking_date', $bookingDate)
            ->where('status', '!=', BookingDetail::STATUS_CANCELLED)
            ->orderBy('booking_date')
            ->orderBy('start_time')
            ->get()
            ->filter(fn (BookingDetail $sessionDetail): bool => ! in_array(
                (string) $sessionDetail->charge_period,
                ['weekly', 'monthly'],
                true
            ))
            ->values();
    }

    private function isFirstTimedSessionDetail(BookingDetail $detail): bool
    {
        $firstDetail = $this->timedSessionDetails($detail)->first();

        return $firstDetail && (int) $firstDetail->getKey() === (int) $detail->getKey();
    }

    private function isLatestTimedSessionDetail(BookingDetail $detail): bool
    {
        $lastDetail = $this->timedSessionDetails($detail)->last();

        return $lastDetail && (int) $lastDetail->getKey() === (int) $detail->getKey();
    }

    private function sessionHasStarted(BookingDetail $detail): bool
    {
        return $this->timedSessionDetails($detail)
            ->contains(fn (BookingDetail $sessionDetail): bool => (bool) $sessionDetail->actual_start_at && ! $sessionDetail->actual_end_at);
    }

    private function extensionOverlapsExistingBooking(BookingDetail $detail, Carbon $extensionStart, Carbon $extensionEnd): bool
    {
        $roomId = $detail->hyve_room_id;

        if (! $roomId) {
            return true;
        }

        return BookingDetail::query()
            ->with('bookingHeader')
            ->where('hyve_room_id', $roomId)
            ->whereIn('status', [BookingDetail::STATUS_PENDING, BookingDetail::STATUS_CONFIRMED])
            ->whereKeyNot($detail->getKey())
            ->get()
            ->contains(function (BookingDetail $otherDetail) use ($extensionStart, $extensionEnd): bool {
                if ($this->isLongStayDetail($otherDetail)) {
                    $otherStartDate = optional($otherDetail->booking_date)?->copy()?->startOfDay();
                    $otherEndDate = optional($otherDetail->booking_end_date ?: $otherDetail->booking_date)?->copy()?->endOfDay();

                    if (! $otherStartDate || ! $otherEndDate) {
                        return false;
                    }

                    return $extensionStart->lt($otherEndDate) && $extensionEnd->gt($otherStartDate);
                }

                $otherRange = $this->detailTimedRange($otherDetail);

                if (! $otherRange) {
                    return false;
                }

                return $extensionStart->lt($otherRange[1]) && $extensionEnd->gt($otherRange[0]);
            });
    }

    private function extensionConflictReason(BookingDetail $detail, Carbon $extensionStart, Carbon $extensionEnd): ?string
    {
        if ($this->extensionOverlapsExistingBooking($detail, $extensionStart, $extensionEnd)) {
            return 'another pending or confirmed booking occupies part of that time.';
        }

        if (! $detail->hyveRoom || ! $this->extensionFitsRoomSchedule($detail->hyveRoom, $extensionStart, $extensionEnd)) {
            return 'the room schedule is closed during part of that time.';
        }

        $calendarEvents = HyveCalendarEvent::query()
            ->with('rooms:id,room_name')
            ->active()
            ->where('affects_booking', true)
            ->whereDate('start_date', '<=', $extensionEnd->toDateString())
            ->whereDate('end_date', '>=', $extensionStart->toDateString())
            ->get();

        foreach ($calendarEvents as $event) {
            if (! $event->appliesToRoom($detail->hyveRoom)) {
                continue;
            }

            [$eventStart, $eventEnd] = $this->extensionCalendarEventRange($event);

            if ($extensionStart->lt($eventEnd) && $extensionEnd->gt($eventStart)) {
                return 'a calendar closure or blocked event occupies part of that time.';
            }
        }

        return null;
    }

    private function extensionFitsRoomSchedule(HyveRoom $room, Carbon $extensionStart, Carbon $extensionEnd): bool
    {
        $windows = [];
        $cursor = $extensionStart->copy();
        $interval = max(1, (int) config('hyve.booking.slot_interval_minutes', 30));

        while ($cursor->lt($extensionEnd)) {
            $slotEnd = $cursor->copy()->addMinutes($interval)->min($extensionEnd);
            $covered = false;

            foreach ([-1, 0] as $dayOffset) {
                $scheduleDate = $cursor->copy()->startOfDay()->addDays($dayOffset)->toDateString();
                $windows[$scheduleDate] ??= $this->extensionScheduleWindow($room, $scheduleDate);
                $window = $windows[$scheduleDate];

                if ($window !== null && $cursor->gte($window[0]) && $slotEnd->lte($window[1])) {
                    $covered = true;
                    break;
                }
            }

            if (! $covered) {
                return false;
            }

            $cursor = $slotEnd;
        }

        return true;
    }

    /** @return array{0: Carbon, 1: Carbon}|null */
    private function extensionScheduleWindow(HyveRoom $room, string $bookingDate): ?array
    {
        if ($this->operatingSchedule->isGloballyClosed($bookingDate)) {
            return null;
        }

        $override = HyveScheduleOverride::query()
            ->whereDate('booking_date', $bookingDate)
            ->where(function ($query) use ($room): void {
                $query->where('hyve_room_id', $room->getKey())->orWhereNull('hyve_room_id');
            })
            ->orderByRaw('case when hyve_room_id is null then 1 else 0 end')
            ->first();

        if ($override?->isClosed()) {
            return null;
        }

        $openingTime = $override?->isCustom() && $override->opening_time
            ? (string) $override->opening_time
            : (string) config('hyve.booking.opening_time', '00:00');
        $closingTime = $override?->isCustom() && $override->closing_time
            ? (string) $override->closing_time
            : (string) config('hyve.booking.closing_time', '24:00');
        $start = $this->extensionTimeBoundary($bookingDate, $openingTime);
        $end = $this->extensionTimeBoundary($bookingDate, $closingTime);

        if ($end->lte($start)) {
            $end->addDay();
        }

        return [$start, $end];
    }

    private function extensionTimeBoundary(string $date, string $time): Carbon
    {
        if ($time === '24:00') {
            return Carbon::parse($date)->startOfDay()->addDay();
        }

        return Carbon::parse($date.' '.$time);
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private function extensionCalendarEventRange(HyveCalendarEvent $event): array
    {
        $startDate = optional($event->start_date)->toDateString() ?? now()->toDateString();
        $endDate = optional($event->end_date)->toDateString() ?? $startDate;

        if ($event->isAllDay()) {
            return [
                Carbon::parse($startDate)->startOfDay(),
                Carbon::parse($endDate)->startOfDay()->addDay(),
            ];
        }

        $start = $this->extensionTimeBoundary($startDate, substr((string) ($event->start_time ?: '00:00'), 0, 5));
        $end = $this->extensionTimeBoundary($endDate, substr((string) ($event->end_time ?: '24:00'), 0, 5));

        if ($end->lte($start)) {
            $end->addDay();
        }

        return [$start, $end];
    }

    /** @return array{new_total: float, new_balance: float} */
    private function extensionFinancialPreview(
        BookingHeader $header,
        BookingDetail $detail,
        array $quote,
        Carbon $extensionStart,
        float $extensionAmount,
    ): array {
        $header->loadMissing('payments');
        $discount = $this->discounts->calculate(
            $header,
            (string) ($header->discount_code ?? HyveDiscountService::NONE),
            [],
            [[
                'subtotal' => $extensionAmount,
                'space_slug' => (string) ($detail->space?->slug ?? ''),
                'charge_period' => (string) ($quote['charge_period'] ?? 'day'),
                'start_time' => $extensionStart->format('H:i'),
                'billed_hours' => (float) ($quote['billed_hours'] ?? 0),
            ]],
        );
        $newTotal = round((float) $discount['discounted_total_amount'], 2);
        $approvedTotal = round(
            (float) $header->payments
                ->where('status', BookingPayment::STATUS_APPROVED)
                ->sum(fn (BookingPayment $payment): float => (float) $payment->amount),
            2
        );

        return [
            'new_total' => $newTotal,
            'new_balance' => round(max(0, $newTotal - $approvedTotal), 2),
        ];
    }

    private function extensionDurationLabel(int $minutes): string
    {
        $hours = intdiv($minutes, 60);
        $remainingMinutes = $minutes % 60;
        $parts = [];

        if ($hours > 0) {
            $parts[] = $hours.' hr'.($hours === 1 ? '' : 's');
        }

        if ($remainingMinutes > 0) {
            $parts[] = $remainingMinutes.' min';
        }

        return '+'.implode(' ', $parts);
    }

    /** @return array<string, mixed> */
    private function openTimeCheckoutCalculation(BookingDetail $detail, BookingHeader $header, Carbon $endedAt): array
    {
        $startedAt = $detail->actual_start_at ?: $endedAt;
        $intervalMinutes = max(1, (int) config('hyve.booking.slot_interval_minutes', 30));
        $minimumMinutes = max($intervalMinutes, (int) config('hyve.booking.minimum_duration_minutes', 120));
        $elapsedMinutes = max(1, $startedAt->diffInMinutes($endedAt));
        $billedMinutes = max($minimumMinutes, (int) ceil($elapsedMinutes / $intervalMinutes) * $intervalMinutes);
        $billingEnd = $startedAt->copy()->addMinutes($billedMinutes);
        $room = $detail->hyveRoom ?: $detail->hyveRoom()->firstOrFail();
        $quote = $this->pricing->quoteForRoom(
            $room,
            $startedAt->toDateString(),
            $startedAt->format('H:i'),
            $billingEnd->format('H:i'),
        );

        if (! $quote) {
            throw ValidationException::withMessages([
                'payment_method' => 'The final Open Time charge could not be computed. Check the active room pricing first.',
            ]);
        }

        $discount = $this->discounts->calculate(
            $header,
            (string) ($header->discount_code ?? HyveDiscountService::NONE),
            [$detail->getKey() => [
                'subtotal' => (float) $quote['total_amount'],
                'charge_period' => (string) $quote['charge_period'],
                'start_time' => $startedAt->format('H:i'),
                'billed_hours' => $billedMinutes / 60,
            ]],
        );
        $grossTotal = (float) $discount['gross_total'];
        $finalTotal = round((float) $discount['discounted_total_amount'], 2);
        $approvedBefore = round((float) $header->payments()
            ->where('status', BookingPayment::STATUS_APPROVED)
            ->sum('amount'), 2);

        return [
            'started_at' => $startedAt,
            'ended_at' => $endedAt,
            'billing_end' => $billingEnd,
            'elapsed_minutes' => $elapsedMinutes,
            'billed_minutes' => $billedMinutes,
            'quote' => $quote,
            'gross_total' => $grossTotal,
            'discount' => $discount,
            'final_total' => $finalTotal,
            'approved_before' => $approvedBefore,
            'amount_due' => round(max(0, $finalTotal - $approvedBefore), 2),
        ];
    }

    /** @param array<string, mixed> $calculation */
    private function openTimeCheckoutPayload(BookingDetail $detail, array $calculation): array
    {
        return [
            'room' => $this->activityRoomName($detail),
            'actual_start' => $calculation['started_at']->format('M j, Y g:i A'),
            'checkout_time' => $calculation['ended_at']->format('M j, Y g:i A'),
            'actual_duration' => $this->plainDurationLabel((int) $calculation['elapsed_minutes']),
            'billed_duration' => $this->plainDurationLabel((int) $calculation['billed_minutes']),
            'gross_total_label' => 'Php '.number_format($calculation['gross_total'], 2),
            'discount_label' => (string) ($detail->bookingHeader?->discount_label ?? 'No discount'),
            'discount_amount_label' => 'Php '.number_format((float) ($calculation['discount']['discount_amount'] ?? 0), 2),
            'final_total_label' => 'Php '.number_format($calculation['final_total'], 2),
            'approved_before_label' => 'Php '.number_format($calculation['approved_before'], 2),
            'amount_due' => $calculation['amount_due'],
            'amount_due_label' => 'Php '.number_format($calculation['amount_due'], 2),
        ];
    }

    private function plainDurationLabel(int $minutes): string
    {
        $hours = intdiv($minutes, 60);
        $remainingMinutes = $minutes % 60;
        $parts = [];

        if ($hours > 0) {
            $parts[] = $hours.' hr'.($hours === 1 ? '' : 's');
        }

        if ($remainingMinutes > 0 || $parts === []) {
            $parts[] = $remainingMinutes.' min';
        }

        return implode(' ', $parts);
    }

    private function syncHeaderFinancialSnapshot(BookingHeader $header): void
    {
        $header->loadMissing(['details.space', 'payments']);
        $discountSnapshot = $this->discounts->calculate(
            $header,
            (string) ($header->discount_code ?? HyveDiscountService::NONE),
        );
        $grossTotal = (float) $discountSnapshot['gross_total'];

        $approvedTotal = round(
            (float) $header->payments
                ->where('status', BookingPayment::STATUS_APPROVED)
                ->sum(fn (BookingPayment $payment): float => (float) ($payment->amount ?? 0)),
            2
        );

        $effectiveTotal = round((float) ($discountSnapshot['discounted_total_amount'] ?? $grossTotal), 2);
        $nextBalance = round(max(0, $effectiveTotal - $approvedTotal), 2);

        $latestApprovedProof = $header->payments
            ->where('status', BookingPayment::STATUS_APPROVED)
            ->sortByDesc(fn (BookingPayment $payment) => optional($payment->verified_at)->timestamp ?? optional($payment->paid_at)->timestamp ?? 0)
            ->first();

        $header->update([
            'total_amount' => $grossTotal,
            'discount_code' => $discountSnapshot['discount_code'],
            'discount_label' => $discountSnapshot['discount_label'],
            'discount_rate' => $discountSnapshot['discount_rate'],
            'discount_amount' => (float) ($discountSnapshot['discount_amount'] ?? 0),
            'discounted_total_amount' => (float) ($discountSnapshot['discounted_total_amount'] ?? $grossTotal),
            'payment_method' => $latestApprovedProof?->payment_method ?? $header->payment_method,
            'payment_proof_path' => $latestApprovedProof?->payment_proof_path ?? $header->payment_proof_path,
            'payment_proof_name' => $latestApprovedProof?->payment_proof_name ?? $header->payment_proof_name,
            'payment_status' => $this->resolveHeaderPaymentStatus($header, $approvedTotal, $nextBalance),
            'downpayment_amount' => $approvedTotal,
            'balance_amount' => $nextBalance,
        ]);
    }

    private function hasPendingPayments(int $bookingHeaderId): bool
    {
        return BookingPayment::query()
            ->where('booking_header_id', $bookingHeaderId)
            ->where('status', BookingPayment::STATUS_PENDING)
            ->exists();
    }

    private function resolveHeaderPaymentStatus(BookingHeader $header, float $approvedTotal, float $nextBalance): string
    {
        if ((string) $header->status === 'cancelled') {
            return 'rejected';
        }

        if ($nextBalance <= 0) {
            return 'paid';
        }

        if ($this->hasPendingPayments($header->getKey())) {
            return 'pending_balance_verification';
        }

        if ($approvedTotal > 0) {
            return 'partially_paid';
        }

        return 'pending_verification';
    }

    private function displayDateTime(Carbon $dateTime): string
    {
        return $dateTime->format('F j, Y g:i A');
    }

    private function progressMeta(BookingDetail $detail, Carbon $scheduledStart, Carbon $scheduledEnd): array
    {
        $status = (string) $detail->status;

        if ($status === BookingDetail::STATUS_CANCELLED) {
            return [
                'key' => 'cancelled',
                'label' => 'Rejected',
                'class' => 'admin-bookings-badge--rejected',
            ];
        }

        if ($status !== BookingDetail::STATUS_CONFIRMED) {
            return [
                'key' => 'pending_review',
                'label' => 'Waiting approval',
                'class' => 'admin-bookings-badge--pending',
            ];
        }

        if ($detail->actual_end_at || (string) $detail->progress_status === BookingDetail::PROGRESS_COMPLETED) {
            return [
                'key' => BookingDetail::PROGRESS_COMPLETED,
                'label' => 'Completed',
                'class' => 'admin-bookings-badge--paid',
            ];
        }

        if ($detail->actual_start_at || (string) $detail->progress_status === BookingDetail::PROGRESS_IN_PROGRESS) {
            return [
                'key' => BookingDetail::PROGRESS_IN_PROGRESS,
                'label' => 'In progress',
                'class' => 'admin-bookings-badge--confirmed',
            ];
        }

        $now = now();

        if ($now->greaterThanOrEqualTo($scheduledStart) && $now->lessThan($scheduledEnd)) {
            return [
                'key' => BookingDetail::PROGRESS_READY,
                'label' => 'Ready to start',
                'class' => 'admin-bookings-badge--partial',
            ];
        }

        return [
            'key' => BookingDetail::PROGRESS_SCHEDULED,
            'label' => 'Scheduled',
            'class' => 'admin-bookings-badge--member',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function notificationPayload(BookingActivity $activity): array
    {
        return [
            'id' => $activity->getKey(),
            'booking_header_id' => $activity->booking_header_id,
            'booking_detail_id' => $activity->booking_detail_id,
            'event_label' => (string) $activity->event_label,
            'message' => (string) $activity->message,
            'customer_name' => (string) ($activity->customer_name ?: 'Customer'),
            'room_name' => $activity->room_name,
            'booking_date' => $activity->booking_date?->format('M j, Y'),
            'time_range' => $activity->time_range,
            'created_at_human' => optional($activity->created_at)->diffForHumans(),
            'is_read' => $activity->read_at !== null,
        ];
    }

    private function unreadOnlineBookingActivitiesQuery()
    {
        return BookingActivity::query()
            ->where('event_key', 'booking_submitted')
            ->whereNull('read_at')
            ->whereHas('bookingHeader', fn ($query) => $query->where('source', BookingHeader::SOURCE_WEB));
    }

    private function markOnlineBookingActivitiesRead(): void
    {
        if (! Schema::hasTable('booking_activities')) {
            return;
        }

        $this->unreadOnlineBookingActivitiesQuery()->update(['read_at' => now()]);
    }

    /**
     * @return array{unread_count:int,activities:array<int, array<string, mixed>>}
     */
    private function bookingNotificationPayload(): array
    {
        $databaseActivities = collect();
        $databaseUnreadCount = 0;

        if (Schema::hasTable('booking_activities')) {
            $databaseActivities = BookingActivity::query()
                ->latest('created_at')
                ->limit(12)
                ->get()
                ->map(fn (BookingActivity $activity): array => $this->notificationPayload($activity));

            $databaseUnreadCount = BookingActivity::query()
                ->whereNull('read_at')
                ->count();
        }

        $reminders = collect($this->upcomingBookingReminders());

        return [
            'unread_count' => $databaseUnreadCount + $reminders->count(),
            'activities' => $reminders
                ->concat($databaseActivities)
                ->take(12)
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array{unread_count:int,activities:Collection<int, mixed>}
     */
    private function initialBookingNotifications(): array
    {
        $databaseActivities = collect();
        $databaseUnreadCount = 0;

        if (Schema::hasTable('booking_activities')) {
            $databaseActivities = BookingActivity::query()
                ->latest('created_at')
                ->limit(12)
                ->get();

            $databaseUnreadCount = BookingActivity::query()
                ->whereNull('read_at')
                ->count();
        }

        $reminders = collect($this->upcomingBookingReminderViewModels());

        return [
            'unread_count' => $databaseUnreadCount + $reminders->count(),
            'activities' => $reminders
                ->concat($databaseActivities)
                ->take(12)
                ->values(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function upcomingBookingReminders(): array
    {
        $now = now();
        $windowEnd = $now->copy()->addMinutes(5);

        $details = BookingDetail::query()
            ->with(['bookingHeader', 'hyveRoom', 'space'])
            ->whereIn('status', [BookingDetail::STATUS_PENDING, BookingDetail::STATUS_CONFIRMED])
            ->whereNull('actual_end_at')
            ->get();

        return $details
            ->flatMap(function (BookingDetail $detail) use ($now, $windowEnd): array {
                $reminders = [];
                $scheduledStart = $this->scheduledDateTime($detail, (string) $detail->start_time);
                $scheduledEnd = $this->scheduledDateTime($detail, (string) $detail->end_time, true);
                $header = $detail->bookingHeader;
                $roomName = $this->activityRoomName($detail);
                $timeLabel = $this->activityTimeRange($detail);

                if (! $detail->actual_start_at && ! $scheduledStart->lt($now) && ! $scheduledStart->gt($windowEnd)) {
                    $minutesLeft = max(0, (int) ceil($now->diffInSeconds($scheduledStart, false) / 60));
                    $isPending = (string) $detail->status === BookingDetail::STATUS_PENDING;

                    $reminders[] = [
                        'id' => ($isPending ? 'approval-reminder-' : 'start-reminder-').$detail->getKey(),
                        'booking_header_id' => $header?->getKey(),
                        'booking_detail_id' => $detail->getKey(),
                        'event_label' => $isPending ? 'Booking approval needed' : 'Booking ready to start',
                        'message' => $isPending
                            ? ($minutesLeft <= 0
                                ? 'This booking is due now and still needs approval.'
                                : 'This booking starts in '.$minutesLeft.' minute(s) and still needs approval.')
                            : ($minutesLeft <= 0
                                ? 'This approved booking should be started now.'
                                : 'This approved booking needs to be started within '.$minutesLeft.' minute(s).'),
                        'customer_name' => (string) ($header?->customer_name ?: 'Customer'),
                        'room_name' => $roomName,
                        'booking_date' => optional($detail->booking_date)->format('M j, Y'),
                        'time_range' => $timeLabel,
                        'starts_at' => $scheduledStart->timestamp,
                        'created_at_human' => $scheduledStart->diffForHumans($now, [
                            'parts' => 2,
                            'short' => false,
                            'syntax' => Carbon::DIFF_RELATIVE_TO_NOW,
                        ]),
                        'is_read' => false,
                    ];
                }

                if ((string) $detail->status === BookingDetail::STATUS_CONFIRMED
                    && $detail->actual_start_at
                    && ! $scheduledEnd->lt($now)
                    && ! $scheduledEnd->gt($windowEnd)) {
                    $minutesLeft = max(0, (int) ceil($now->diffInSeconds($scheduledEnd, false) / 60));

                    $reminders[] = [
                        'id' => 'end-reminder-'.$detail->getKey(),
                        'booking_header_id' => $header?->getKey(),
                        'booking_detail_id' => $detail->getKey(),
                        'event_label' => 'Booking ready to end',
                        'message' => $minutesLeft <= 0
                            ? 'This booking should be ended now.'
                            : 'This active booking needs to be ended within '.$minutesLeft.' minute(s).',
                        'customer_name' => (string) ($header?->customer_name ?: 'Customer'),
                        'room_name' => $roomName,
                        'booking_date' => optional($detail->booking_date)->format('M j, Y'),
                        'time_range' => $timeLabel,
                        'starts_at' => $scheduledEnd->timestamp,
                        'created_at_human' => $scheduledEnd->diffForHumans($now, [
                            'parts' => 2,
                            'short' => false,
                            'syntax' => Carbon::DIFF_RELATIVE_TO_NOW,
                        ]),
                        'is_read' => false,
                    ];
                }

                return $reminders;
            })
            ->sortBy(fn (array $reminder): int => (int) ($reminder['starts_at'] ?? 0))
            ->map(function (array $reminder): array {
                unset($reminder['starts_at']);

                return $reminder;
            })
            ->values()
            ->all();
    }

    private function syncDueBookingsProgress(): void
    {
        $this->progressSync->sync(request()->user()?->getKey());
    }

    /**
     * @return list<object>
     */
    private function upcomingBookingReminderViewModels(): array
    {
        $now = now();
        $windowEnd = $now->copy()->addMinutes(5);

        $details = BookingDetail::query()
            ->with(['bookingHeader', 'hyveRoom', 'space'])
            ->whereIn('status', [BookingDetail::STATUS_PENDING, BookingDetail::STATUS_CONFIRMED])
            ->whereNull('actual_end_at')
            ->get();

        return $details
            ->flatMap(function (BookingDetail $detail) use ($now, $windowEnd): array {
                $reminders = [];
                $scheduledStart = $this->scheduledDateTime($detail, (string) $detail->start_time);
                $scheduledEnd = $this->scheduledDateTime($detail, (string) $detail->end_time, true);
                $header = $detail->bookingHeader;

                if (! $detail->actual_start_at && ! $scheduledStart->lt($now) && ! $scheduledStart->gt($windowEnd)) {
                    $minutesLeft = max(0, (int) ceil($now->diffInSeconds($scheduledStart, false) / 60));
                    $isPending = (string) $detail->status === BookingDetail::STATUS_PENDING;

                    $reminders[] = (object) [
                        'event_label' => $isPending ? 'Booking approval needed' : 'Booking ready to start',
                        'message' => $isPending
                            ? ($minutesLeft <= 0
                                ? 'This booking is due now and still needs approval.'
                                : 'This booking starts in '.$minutesLeft.' minute(s) and still needs approval.')
                            : ($minutesLeft <= 0
                                ? 'This approved booking should be started now.'
                                : 'This approved booking needs to be started within '.$minutesLeft.' minute(s).'),
                        'customer_name' => (string) ($header?->customer_name ?: 'Customer'),
                        'room_name' => $this->activityRoomName($detail),
                        'booking_date' => $detail->booking_date,
                        'time_range' => $this->activityTimeRange($detail),
                        'created_at' => $scheduledStart,
                        'read_at' => null,
                    ];
                }

                if ((string) $detail->status === BookingDetail::STATUS_CONFIRMED
                    && $detail->actual_start_at
                    && ! $scheduledEnd->lt($now)
                    && ! $scheduledEnd->gt($windowEnd)) {
                    $minutesLeft = max(0, (int) ceil($now->diffInSeconds($scheduledEnd, false) / 60));

                    $reminders[] = (object) [
                        'event_label' => 'Booking ready to end',
                        'message' => $minutesLeft <= 0
                            ? 'This booking should be ended now.'
                            : 'This active booking needs to be ended within '.$minutesLeft.' minute(s).',
                        'customer_name' => (string) ($header?->customer_name ?: 'Customer'),
                        'room_name' => $this->activityRoomName($detail),
                        'booking_date' => $detail->booking_date,
                        'time_range' => $this->activityTimeRange($detail),
                        'created_at' => $scheduledEnd,
                        'read_at' => null,
                    ];
                }

                return $reminders;
            })
            ->filter()
            ->sortBy(fn (object $reminder): int => optional($reminder->created_at)?->timestamp ?? 0)
            ->values()
            ->all();
    }

    /**
     * @return array{search:string,view:string,type:string,method:string}
     */
    private function bookingFilters(Request $request): array
    {
        return [
            'search' => trim((string) $request->query('search', '')),
            'view' => (string) $request->query('view', 'all'),
            'type' => (string) $request->query('type', 'all'),
            'method' => (string) $request->query('method', 'all'),
        ];
    }

    /**
     * @param  array{search:string,view:string,type:string,method:string}  $filters
     */
    private function bookingListingPaginator(Request $request, array $filters)
    {
        $paginator = $this->bookingHeadersQuery($filters)
            ->latest('created_at')
            ->paginate(10)
            ->withQueryString();
        $identityStats = $this->bookingIdentityStats();

        return $paginator->through(function (BookingHeader $header) use ($identityStats): array {
            $stats = $identityStats->get($this->bookingContactIdentity($header->phone, $header->email), []);

            return $this->bookingRowPayload(
                $header,
                ($stats['count'] ?? 0) > 1,
                (int) ($stats['latest_id'] ?? 0) === (int) $header->getKey(),
            );
        });
    }

    /**
     * @param  array{search:string,view:string,type:string,method:string}  $filters
     */
    private function bookingHeadersQuery(array $filters)
    {
        return BookingHeader::query()
            ->with(['details.hyveRoom', 'details.space', 'user', 'wifiVoucher'])
            ->when($filters['search'] !== '', function ($query) use ($filters) {
                $search = $filters['search'];

                $query->where(function ($builder) use ($search) {
                    $builder
                        ->where('reference_no', 'like', '%'.$search.'%')
                        ->orWhere('customer_name', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%')
                        ->orWhere('phone', 'like', '%'.$search.'%');

                    if (ctype_digit($search)) {
                        $builder->orWhere('booking_headers.id', (int) $search);
                    }
                });
            })
            ->when($filters['view'] === 'pending', fn ($query) => $query->where('status', BookingHeader::STATUS_PENDING))
            ->when($filters['view'] === 'approved', fn ($query) => $query->where('status', 'confirmed'))
            ->when($filters['view'] === 'rejected', fn ($query) => $query->where('status', 'cancelled'))
            ->when($filters['view'] === 'paid', fn ($query) => $query->where('payment_status', 'paid'))
            ->when($filters['view'] === 'with_balance', fn ($query) => $query->where('balance_amount', '>', 0))
            ->when($filters['type'] !== 'all', fn ($query) => $query->where('booking_type', $filters['type']))
            ->when($filters['method'] !== 'all', fn ($query) => $query->where('payment_method', $filters['method']));
    }

    /**
     * @return array<string, mixed>
     */
    private function bookingRowPayload(BookingHeader $header, ?bool $isReturning = null, ?bool $isLatestForCustomer = null): array
    {
        if ($isReturning === null || $isLatestForCustomer === null) {
            $identity = $this->bookingContactIdentity($header->phone, $header->email);
            $stats = $this->bookingIdentityStats()->get($identity, []);
            $isReturning ??= ($stats['count'] ?? 0) > 1;
            $isLatestForCustomer ??= (int) ($stats['latest_id'] ?? 0) === (int) $header->getKey();
        }

        $canManageBookings = request()->user()?->hasPermission('bookings.manage') ?? false;

        $bookingSummaries = $header->details
            ->sortBy([
                ['booking_date', 'asc'],
                ['start_time', 'asc'],
            ])
            ->map(function (BookingDetail $detail) use ($header, $canManageBookings): array {
                $sessionDetails = $this->timedSessionDetails($detail);
                $sessionStartDetail = $sessionDetails->first();
                $sessionLatestDetail = $sessionDetails->last();
                $isLatestSessionDetail = $sessionLatestDetail
                    && (int) $sessionLatestDetail->getKey() === (int) $detail->getKey();
                $canStartSession = $canManageBookings
                    && $isLatestSessionDetail
                    && $sessionStartDetail
                    && $this->canStartDetail($sessionStartDetail);
                $canReschedule = $canManageBookings
                    && $isLatestSessionDetail
                    && $this->rescheduleService->canReschedule($detail);
                $scheduledStart = $this->scheduledDateTime($detail, (string) $detail->start_time);
                $scheduledEnd = $this->scheduledDateTime($detail, (string) $detail->end_time, true);
                $progressMeta = $this->progressMeta($detail, $scheduledStart, $scheduledEnd);
                $dateLabel = $this->detailDateLabel($detail);
                $timeLabel = $this->detailTimeLabel($detail);
                $scheduledStartLabel = $this->detailScheduledStartLabel($detail, $scheduledStart);
                $scheduledEndLabel = $this->detailScheduledEndLabel($detail, $scheduledEnd);

                $detailStatus = (string) ($detail->status ?? BookingDetail::STATUS_PENDING);

                $statusLabel = match ($detailStatus) {
                    'confirmed' => 'Approved',
                    'cancelled' => 'Rejected',
                    default => 'Pending',
                };

                $statusClass = match ($detailStatus) {
                    'confirmed' => 'admin-bookings-badge--confirmed',
                    'cancelled' => 'admin-bookings-badge--rejected',
                    default => 'admin-bookings-badge--pending',
                };

                return [
                    'id' => $detail->getKey(),
                    'header_id' => $header->getKey(),
                    'reference' => $header->reference_no,
                    'room' => $detail->hyveRoom?->room_name ?? $detail->space?->name ?? 'Room',
                    'date' => $dateLabel,
                    'time' => $timeLabel,
                    'scheduled_start' => $scheduledStartLabel,
                    'scheduled_end' => $scheduledEndLabel,
                    'actual_start' => optional($detail->actual_start_at)?->format('F j, Y g:i A') ?? '--',
                    'actual_end' => optional($detail->actual_end_at)?->format('F j, Y g:i A') ?? '--',
                    'is_open_time' => (bool) $detail->is_open_time,
                    'open_time_cutoff' => $detail->is_open_time ? $scheduledEndLabel : null,
                    'progress' => $progressMeta['label'],
                    'progress_class' => $progressMeta['class'],
                    'progress_key' => $progressMeta['key'],
                    'booking_type' => ucfirst((string) $header->booking_type),
                    'payment_method' => ucfirst(str_replace('_', ' ', (string) $header->payment_method)),
                    'status' => $statusLabel,
                    'status_class' => $statusClass,
                    'payment_status' => $this->paymentStatusLabel((string) ($header->payment_status ?? 'pending_verification')),
                    'payment_status_key' => (string) ($header->payment_status ?? 'pending_verification'),
                    'payment_status_class' => $this->paymentStatusClass((string) ($header->payment_status ?? 'pending_verification')),
                    'amount' => 'Php '.number_format((float) ($detail->subtotal ?? 0), 2),
                    'header_total' => 'Php '.number_format((float) ($header->total_amount ?? 0), 2),
                    'downpayment' => 'Php '.number_format((float) ($header->downpayment_amount ?? 0), 2),
                    'balance' => 'Php '.number_format((float) ($header->balance_amount ?? 0), 2),
                    'proof' => $header->payment_proof_path ? route('admin.bookings.proof', ['bookingHeader' => $header->getKey()]) : null,
                    'proof_visible' => (bool) $header->payment_proof_path,
                    'long_stay_plan' => $detail->long_stay_plan_label,
                    'student_id_reference' => $detail->student_id_reference,
                    'student_id_proof' => $detail->student_id_proof_path
                        ? route('admin.booking-details.student-id-proof', ['bookingDetail' => $detail->getKey()])
                        : null,
                    'approve_url' => $canManageBookings ? route('admin.booking-details.approve', ['bookingDetail' => $detail->getKey()]) : null,
                    'reject_url' => $canManageBookings ? route('admin.booking-details.reject', ['bookingDetail' => $detail->getKey()]) : null,
                    'start_url' => $canStartSession
                        ? route('admin.booking-details.start', ['bookingDetail' => $sessionStartDetail->getKey()])
                        : null,
                    'end_url' => $canManageBookings ? route('admin.booking-details.end', ['bookingDetail' => $detail->getKey()]) : null,
                    'open_time_checkout_preview_url' => $canManageBookings && $detail->is_open_time
                        ? route('admin.booking-details.open-time-checkout-preview', ['bookingDetail' => $detail->getKey()])
                        : null,
                    'extend_url' => $canManageBookings ? route('admin.booking-details.extend', ['bookingDetail' => $detail->getKey()]) : null,
                    'extension_options_url' => $canManageBookings ? route('admin.booking-details.extension-options', ['bookingDetail' => $detail->getKey()]) : null,
                    'can_review' => $canManageBookings && ! in_array($detailStatus, ['confirmed', 'cancelled'], true),
                    'can_start' => $canStartSession,
                    'can_end' => $canManageBookings
                        && $isLatestSessionDetail
                        && ($detail->is_open_time ? $this->canCheckoutOpenTimeDetail($detail) : $this->canEndDetail($detail)),
                    'can_extend' => $canManageBookings && $isLatestSessionDetail && $this->canExtendDetail($detail),
                    'can_reschedule' => $canReschedule,
                    'reschedule_url' => $canReschedule
                        ? route('admin.booking-details.reschedule', ['bookingDetail' => $detail->getKey()])
                        : null,
                    'end_time_value' => substr((string) $detail->end_time, 0, 5),
                    'created_at' => optional($header->created_at)->format('M j, Y g:i A'),
                ];
            })
            ->values();

        $previewRooms = $bookingSummaries
            ->pluck('room')
            ->filter()
            ->unique()
            ->values();
        $hasLongStayBooking = $header->details->contains(fn (BookingDetail $detail): bool => $this->isLongStayDetail($detail));
        $previewSummary = $hasLongStayBooking
            ? $header->details
                ->map(fn (BookingDetail $detail): string => ($detail->hyveRoom?->room_name ?? $detail->space?->name ?? 'Room').' - '.$this->detailDateLabel($detail))
                ->unique()
                ->take(2)
                ->values()
                ->implode(', ')
            : '';

        $displayStatus = match ((string) $header->status) {
            'confirmed' => 'Confirmed',
            'cancelled' => 'Rejected',
            default => 'Pending',
        };

        return [
            'id' => $header->getKey(),
            'customer_name' => $header->customer_name,
            'email' => $header->email,
            'phone' => $header->phone,
            'source_label' => $header->source === BookingHeader::SOURCE_ADMIN ? 'Walk-in' : 'Online',
            'source_key' => $header->source === BookingHeader::SOURCE_ADMIN ? 'walk_in' : 'online',
            'booking_type' => ucfirst((string) $header->booking_type),
            'payment_method' => ucfirst(str_replace('_', ' ', (string) $header->payment_method)),
            'reference' => $header->reference_no,
            'booking_count' => $bookingSummaries->count(),
            'slot_count' => $bookingSummaries->count(),
            'latest_timestamp' => optional($header->created_at)?->timestamp ?? 0,
            'latest_date' => optional($header->created_at)->format('M j, Y'),
            'latest_time' => optional($header->created_at)->format('g:i A'),
            'is_new' => $isLatestForCustomer
                && $header->status === BookingHeader::STATUS_PENDING
                && optional($header->created_at)?->greaterThanOrEqualTo(now()->subDay()),
            'is_returning' => $isReturning,
            'total_amount' => 'Php '.number_format((float) ($header->total_amount ?? 0), 2),
            'downpayment_amount' => 'Php '.number_format((float) ($header->downpayment_amount ?? 0), 2),
            'balance_amount' => 'Php '.number_format((float) ($header->balance_amount ?? 0), 2),
            'status' => $displayStatus,
            'status_class' => $this->headerStatusClass((string) $header->status),
            'payment_status' => $this->paymentStatusLabel((string) ($header->payment_status ?? 'pending_verification')),
            'payment_status_key' => (string) ($header->payment_status ?? 'pending_verification'),
            'payment_status_class' => $this->paymentStatusClass((string) ($header->payment_status ?? 'pending_verification')),
            'wifi_voucher' => $this->wifiVoucherService->payloadForBooking($header->loadMissing('wifiVoucher')),
            'has_long_stay' => $hasLongStayBooking,
            'preview_summary' => $previewSummary,
            'bookings' => $bookingSummaries->all(),
            'preview_rooms' => $previewRooms->take(3)->all(),
        ];
    }

    /** @return Collection<string, array{count:int,latest_id:int}> */
    private function bookingIdentityStats(): Collection
    {
        return BookingHeader::query()
            ->latest('created_at')
            ->latest('id')
            ->get(['id', 'phone', 'email'])
            ->groupBy(fn (BookingHeader $header): string => $this->bookingContactIdentity($header->phone, $header->email))
            ->filter(fn (Collection $headers, string $identity): bool => $identity !== '')
            ->map(fn (Collection $headers): array => [
                'count' => $headers->count(),
                'latest_id' => (int) $headers->first()->getKey(),
            ]);
    }

    private function bookingContactIdentity(?string $phone, ?string $email): string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone) ?? '';

        if (str_starts_with($digits, '09')) {
            $digits = '63'.substr($digits, 1);
        } elseif (strlen($digits) === 10 && str_starts_with($digits, '9')) {
            $digits = '63'.$digits;
        }

        if (strlen($digits) >= 7) {
            return 'phone:'.$digits;
        }

        $normalizedEmail = strtolower(trim((string) $email));

        return $normalizedEmail !== '' ? 'email:'.$normalizedEmail : '';
    }

    /** @return array<string, mixed> */
    private function validatedRescheduleData(Request $request, BookingDetail $bookingDetail): array
    {
        $isLongStay = $this->rescheduleService->isLongStay($bookingDetail);
        $rules = [
            'hyve_room_id' => ['required', 'integer', Rule::exists('hyve_rooms', 'id')->where(fn ($query) => $query->where('status', 0))],
            'booking_date' => ['required', 'date', 'after_or_equal:today'],
            'confirm_price_change' => ['sometimes', 'boolean'],
        ];

        if ($isLongStay) {
            $rules['booking_end_date'] = ['required', 'date', 'after_or_equal:booking_date'];
            $rules['long_stay_use_type'] = ['nullable', Rule::in(['day', 'night'])];
        } else {
            $rules['start_time'] = ['required', 'regex:/^(?:[01]\d|2[0-3]):[0-5]\d$/'];
            $rules['end_time'] = ['required', 'regex:/^(?:(?:[01]\d|2[0-3]):[0-5]\d|24:00)$/'];
        }

        return $request->validate($rules, [], [
            'hyve_room_id' => 'room',
            'booking_date' => 'booking date',
            'booking_end_date' => 'end date',
            'start_time' => 'start time',
            'end_time' => 'end time',
            'long_stay_use_type' => 'use period',
        ]);
    }

    /** @param array<string, mixed> $result */
    private function sendRescheduleNotifications(array $result): void
    {
        /** @var BookingHeader $header */
        $header = $result['header'];
        $context = [
            'customer_name' => (string) $header->customer_name,
            'reference_no' => (string) $header->reference_no,
            'old_room' => (string) $result['old_room'],
            'old_schedule' => (string) $result['old_schedule'],
            'new_room' => (string) $result['new_room'],
            'new_schedule' => (string) $result['new_schedule'],
            'total_amount' => (float) $result['new_effective_total'],
            'paid_amount' => (float) $result['approved_total'],
            'balance_amount' => (float) $result['new_balance'],
            'overpayment' => (float) $result['overpayment'],
        ];
        $email = trim((string) $header->email);
        $phone = trim((string) $header->phone);

        if ($email !== '') {
            try {
                Mail::to($email)->send(new BookingRescheduledMail($context));
            } catch (\Throwable $exception) {
                Log::warning('Failed to send booking reschedule email.', [
                    'reference_no' => $header->reference_no,
                    'email' => $email,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        if ($phone !== '') {
            app(BookingRescheduledTextService::class)->send($phone, $context);
        }
    }

    private function syncWifiVoucher(BookingHeader $bookingHeader): void
    {
        if ((string) $bookingHeader->status === 'confirmed') {
            $this->wifiVoucherService->ensureVoucherForBooking($bookingHeader);

            return;
        }

        if ((string) $bookingHeader->status === 'cancelled') {
            $this->wifiVoucherService->revokeVoucherForBooking($bookingHeader);
        }
    }

    private function paymentStatusLabel(string $paymentStatus): string
    {
        return match ($paymentStatus) {
            'paid' => 'Fully Paid',
            'partially_paid' => 'Partially Paid',
            'pending_balance_verification' => 'Payment Submitted',
            'rejected' => 'Payment Rejected',
            default => 'Waiting Payment',
        };
    }

    private function recordActivity(
        BookingHeader $bookingHeader,
        ?BookingDetail $bookingDetail,
        string $eventKey,
        string $eventLabel,
        string $message
    ): void {
        if (! Schema::hasTable('booking_activities')) {
            return;
        }

        BookingActivity::query()->create([
            'booking_header_id' => $bookingHeader->getKey(),
            'booking_detail_id' => $bookingDetail?->getKey(),
            'actor_user_id' => request()->user()?->getKey(),
            'event_key' => $eventKey,
            'event_label' => $eventLabel,
            'reference_no' => $bookingHeader->reference_no,
            'customer_name' => $bookingHeader->customer_name,
            'room_name' => $bookingDetail ? $this->activityRoomName($bookingDetail) : null,
            'booking_date' => $bookingDetail?->booking_date,
            'time_range' => $bookingDetail ? $this->activityTimeRange($bookingDetail) : null,
            'message' => $message,
        ]);
    }

    private function activityRoomName(BookingDetail $bookingDetail): string
    {
        return $bookingDetail->hyveRoom?->room_name ?? $bookingDetail->space?->name ?? 'Room';
    }

    private function resolvedBookingApprovalPaymentStatus(BookingHeader $bookingHeader): string
    {
        $approvedTotal = round(
            (float) BookingPayment::query()
                ->where('booking_header_id', $bookingHeader->getKey())
                ->where('status', BookingPayment::STATUS_APPROVED)
                ->sum('amount'),
            2
        );

        if ($bookingHeader->effectiveTotalAmount() > 0 && $approvedTotal >= $bookingHeader->effectiveTotalAmount()) {
            return 'paid';
        }

        if (BookingPayment::query()
            ->where('booking_header_id', $bookingHeader->getKey())
            ->where('status', BookingPayment::STATUS_PENDING)
            ->exists()) {
            return 'pending_balance_verification';
        }

        if ($approvedTotal > 0) {
            return 'partially_paid';
        }

        return (string) ($bookingHeader->payment_status ?: 'pending_verification');
    }

    private function activityTimeRange(BookingDetail $bookingDetail): string
    {
        if ($this->isLongStayDetail($bookingDetail)) {
            $startDate = optional($bookingDetail->booking_date)?->format('M j, Y') ?? '--';
            $endDate = optional($bookingDetail->booking_end_date ?: $bookingDetail->booking_date)?->format('M j, Y') ?? '--';

            return $startDate === $endDate
                ? $startDate
                : $startDate.' - '.$endDate;
        }

        return Carbon::parse((string) $bookingDetail->start_time)->format('g:i A')
            .' - '
            .Carbon::parse((string) $bookingDetail->end_time)->format('g:i A');
    }

    private function sendApprovalNotifications(BookingHeader $bookingHeader, ?BookingDetail $bookingDetail = null): void
    {
        $email = trim((string) $bookingHeader->email);
        $phone = trim((string) $bookingHeader->phone);
        $context = $this->approvalNotificationContext($bookingHeader, $bookingDetail);

        if ($email !== '') {
            try {
                Mail::to($email)->send(new BookingApprovedMail($context));
            } catch (\Throwable $exception) {
                Log::warning('Failed to send booking approval email.', [
                    'reference_no' => $bookingHeader->reference_no,
                    'email' => $email,
                    'detail_id' => $bookingDetail?->getKey(),
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        if ($phone !== '') {
            app(BookingApprovalTextService::class)->send($phone, $context);
        }
    }

    private function sendRejectionNotifications(BookingHeader $bookingHeader, ?BookingDetail $bookingDetail = null): void
    {
        $email = trim((string) $bookingHeader->email);
        $context = $this->rejectionNotificationContext($bookingHeader, $bookingDetail);

        if ($email === '') {
            return;
        }

        try {
            Mail::to($email)->send(new BookingRejectedMail($context));
        } catch (\Throwable $exception) {
            Log::warning('Failed to send booking rejection email.', [
                'reference_no' => $bookingHeader->reference_no,
                'email' => $email,
                'detail_id' => $bookingDetail?->getKey(),
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function sendPaymentReceiptEmail(?BookingHeader $header): void
    {
        if (! $header || trim((string) $header->email) === '') {
            return;
        }

        $header->loadMissing(['details.hyveRoom', 'details.space', 'payments']);
        $approvedPayments = $header->payments
            ->where('status', BookingPayment::STATUS_APPROVED)
            ->sortBy(fn (BookingPayment $payment) => optional($payment->verified_at ?? $payment->paid_at ?? $payment->created_at)->timestamp ?? 0)
            ->values();
        $latestPayment = $approvedPayments->last();
        $details = $header->details
            ->where('status', '!=', BookingDetail::STATUS_CANCELLED)
            ->sortBy([['booking_date', 'asc'], ['start_time', 'asc']])
            ->values();

        $context = [
            'customer_name' => (string) $header->customer_name,
            'reference_no' => (string) $header->reference_no,
            'payment_method' => ucfirst(str_replace('_', ' ', (string) ($latestPayment?->payment_method ?? $header->payment_method ?? 'cash'))),
            'paid_at' => optional($latestPayment?->verified_at ?? $latestPayment?->paid_at ?? now())->format('F j, Y g:i A'),
            'payment_amount' => round((float) ($latestPayment?->amount ?? 0), 2),
            'gross_total_amount' => round((float) ($header->total_amount ?? 0), 2),
            'discount_label' => (string) ($header->discount_label ?? 'No discount'),
            'discount_amount' => round((float) ($header->discount_amount ?? 0), 2),
            'payable_total_amount' => round((float) ($header->discounted_total_amount ?? $header->total_amount ?? 0), 2),
            'downpayment_amount' => round((float) ($header->downpayment_amount ?? 0), 2),
            'total_paid_amount' => round((float) $approvedPayments->sum('amount'), 2),
            'balance_amount' => round((float) ($header->balance_amount ?? 0), 2),
            'payment_lines' => $approvedPayments->map(function (BookingPayment $payment) use ($latestPayment): array {
                return [
                    'label' => $latestPayment?->is($payment) ? 'Final payment' : 'Downpayment',
                    'method' => ucfirst(str_replace('_', ' ', (string) ($payment->payment_method ?? 'cash'))),
                    'paid_at' => optional($payment->verified_at ?? $payment->paid_at ?? $payment->created_at)->format('F j, Y g:i A') ?? '--',
                    'amount' => round((float) ($payment->amount ?? 0), 2),
                ];
            })->all(),
            'lines' => $details->map(fn (BookingDetail $detail): array => [
                'room_name' => $this->activityRoomName($detail),
                'date' => optional($detail->booking_date)->format('F j, Y') ?? '--',
                'time' => optional($detail->actual_start_at)->format('g:i A')
                    .' - '.optional($detail->actual_end_at)->format('g:i A'),
            ])->all(),
        ];

        try {
            Mail::to((string) $header->email)->send(new BookingPaymentReceiptMail($context));
        } catch (\Throwable $exception) {
            Log::warning('Failed to send Open Time payment receipt email.', [
                'reference_no' => $header->reference_no,
                'email' => $header->email,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function approvalNotificationContext(BookingHeader $bookingHeader, ?BookingDetail $bookingDetail = null): array
    {
        $lineDetails = $bookingDetail
            ? collect([$bookingDetail])
            : $bookingHeader->details
                ->where('status', BookingDetail::STATUS_CONFIRMED)
                ->values();

        $summaryDetails = $bookingHeader->details
            ->where('status', '!=', BookingDetail::STATUS_CANCELLED)
            ->values();

        if ($summaryDetails->isEmpty()) {
            $summaryDetails = $lineDetails;
        }

        $detailsTotal = round(
            (float) $summaryDetails->sum(static fn (BookingDetail $detail): float => (float) ($detail->subtotal ?? 0)),
            2
        );

        $headerTotal = $bookingHeader->effectiveTotalAmount();

        // Use the booking header total as the canonical value shown to customers.
        $computedTotal = $headerTotal > 0 ? $headerTotal : $detailsTotal;

        $downpaymentAmount = round((float) ($bookingHeader->downpayment_amount ?? 0), 2);
        $balanceAmount = round((float) ($bookingHeader->balance_amount ?? 0), 2);

        if ($computedTotal > 0 && $balanceAmount <= 0) {
            $balanceAmount = round(max(0, $computedTotal - $downpaymentAmount), 2);
        }

        $lines = $lineDetails
            ->map(function (BookingDetail $detail): array {
                $roomName = $this->activityRoomName($detail);

                return [
                    'room_name' => $roomName,
                    'room' => $roomName,
                    'date' => $this->detailDateLabel($detail),
                    'time' => $this->detailTimeLabel($detail),
                ];
            })
            ->all();

        return [
            'customer_name' => (string) $bookingHeader->customer_name,
            'reference_no' => (string) $bookingHeader->reference_no,
            'email' => (string) $bookingHeader->email,
            'phone' => (string) $bookingHeader->phone,
            'payment_method' => ucfirst(str_replace('_', ' ', (string) $bookingHeader->payment_method)),
            'booking_type' => ucfirst((string) $bookingHeader->booking_type),
            'total_amount' => $computedTotal,
            'downpayment_amount' => $downpaymentAmount,
            'balance_amount' => $balanceAmount,
            'booking_count' => count($lines),
            'lines' => $lines,
            'single_line' => count($lines) === 1 ? $lines[0] : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function rejectionNotificationContext(BookingHeader $bookingHeader, ?BookingDetail $bookingDetail = null): array
    {
        $lineDetails = $bookingDetail
            ? collect([$bookingDetail])
            : $bookingHeader->details->values();

        $lines = $lineDetails
            ->map(function (BookingDetail $detail): array {
                $roomName = $this->activityRoomName($detail);

                return [
                    'room_name' => $roomName,
                    'room' => $roomName,
                    'date' => $this->detailDateLabel($detail),
                    'time' => $this->detailTimeLabel($detail),
                ];
            })
            ->all();

        return [
            'customer_name' => (string) $bookingHeader->customer_name,
            'reference_no' => (string) $bookingHeader->reference_no,
            'email' => (string) $bookingHeader->email,
            'payment_method' => ucfirst(str_replace('_', ' ', (string) $bookingHeader->payment_method)),
            'booking_type' => ucfirst((string) $bookingHeader->booking_type),
            'booking_count' => count($lines),
            'lines' => $lines,
            'single_line' => count($lines) === 1 ? $lines[0] : null,
        ];
    }
}
