<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\BookingApprovedMail;
use App\Mail\BookingRejectedMail;
use App\Models\BookingActivity;
use App\Models\BookingDetail;
use App\Models\BookingHeader;
use App\Models\BookingPayment;
use App\Services\BookingApprovalTextService;
use App\Services\BookingWifiVoucherService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class AdminBookingController extends Controller
{
    public function __construct(
        private readonly BookingWifiVoucherService $wifiVoucherService,
    ) {
    }

    public function index(Request $request): View
    {
        $this->syncDueBookingsProgress();

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

    public function summary(BookingHeader $bookingHeader): JsonResponse
    {
        $this->syncDueBookingsProgress();

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
            return response()->json([
                'message' => 'Booked line approved successfully.',
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
            return response()->json([
                'message' => 'Booked line rejected successfully.',
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
            return response()->json([
                'message' => 'Booked line started successfully.',
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
        if (! $this->canEndDetail($bookingDetail)) {
            return $this->detailActionErrorResponse($request, 'This booked line cannot be ended yet.');
        }

        $bookingDetail->update([
            'progress_status' => BookingDetail::PROGRESS_COMPLETED,
            'actual_end_at' => now(),
        ]);

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
            return response()->json([
                'message' => 'Booked line ended successfully.',
                'detail' => [
                    'id' => $bookingDetail->getKey(),
                    ...$this->detailProgressPayload($bookingDetail->fresh()),
                ],
            ]);
        }

        return back()->with('admin_success', 'Booked line ended successfully.');
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
        $this->syncDueBookingsProgress();

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

    private function scheduledDateTime(BookingDetail $detail, string $time): Carbon
    {
        return Carbon::parse(optional($detail->booking_date)->format('Y-m-d').' '.$time);
    }

    private function isLongStayDetail(BookingDetail $detail): bool
    {
        $startDate = $detail->booking_date;
        $endDate = $detail->booking_end_date;

        return in_array((string) $detail->charge_period, ['daily', 'weekly', 'monthly'], true)
            || ($endDate && $startDate && $endDate->ne($startDate));
    }

    private function detailDateLabel(BookingDetail $detail): string
    {
        $startDate = $detail->booking_date;
        $endDate = $detail->booking_end_date ?: $startDate;

        if (! $startDate) {
            return '--';
        }

        if ($this->isLongStayDetail($detail) && $endDate && $endDate->ne($startDate)) {
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

        return Carbon::parse((string) $detail->start_time)->format('g:i A')
            .' - '
            .Carbon::parse((string) $detail->end_time)->format('g:i A');
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

    private function canStartDetail(BookingDetail $detail): bool
    {
        return (string) $detail->status === BookingDetail::STATUS_CONFIRMED
            && ! $detail->actual_start_at
            && ! $detail->actual_end_at;
    }

    private function canEndDetail(BookingDetail $detail): bool
    {
        return (string) $detail->status === BookingDetail::STATUS_CONFIRMED
            && (string) $detail->progress_status !== BookingDetail::PROGRESS_COMPLETED
            && (bool) $detail->actual_start_at;
    }

    private function detailProgressPayload(BookingDetail $detail): array
    {
        $scheduledStart = $this->scheduledDateTime($detail, (string) $detail->start_time);
        $scheduledEnd = $this->scheduledDateTime($detail, (string) $detail->end_time);
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
     * @return array{unread_count:int,activities:\Illuminate\Support\Collection<int, mixed>}
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
                $scheduledEnd = $this->scheduledDateTime($detail, (string) $detail->end_time);
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
        $now = now();

        BookingDetail::query()
            ->with(['bookingHeader', 'hyveRoom', 'space'])
            ->where('status', BookingDetail::STATUS_CONFIRMED)
            ->whereNull('actual_end_at')
            ->get()
            ->each(function (BookingDetail $detail) use ($now): void {
                $scheduledStart = $this->scheduledDateTime($detail, (string) $detail->start_time);
                $scheduledEnd = $this->scheduledDateTime($detail, (string) $detail->end_time);
                $header = $detail->bookingHeader;

                if (! $detail->actual_start_at && $scheduledStart->lte($now) && $scheduledEnd->gt($now)) {
                    $detail->update([
                        'progress_status' => BookingDetail::PROGRESS_IN_PROGRESS,
                        'actual_start_at' => $scheduledStart,
                        'actual_end_at' => null,
                    ]);

                    if ($header) {
                        $this->recordActivity(
                            $header,
                            $detail,
                            'booking_auto_started',
                            'Booking auto-started',
                            'Auto-started '.$this->activityRoomName($detail).' for '.$header->customer_name.'.'
                        );
                    }
                }

                if ($scheduledEnd->lte($now)) {
                    $detail->update([
                        'progress_status' => BookingDetail::PROGRESS_COMPLETED,
                        'actual_start_at' => $detail->actual_start_at ?: $scheduledStart,
                        'actual_end_at' => $scheduledEnd,
                    ]);

                    if ($header) {
                        $this->recordActivity(
                            $header,
                            $detail,
                            'booking_auto_completed',
                            'Booking auto-ended',
                            'Auto-ended '.$this->activityRoomName($detail).' for '.$header->customer_name.'.'
                        );
                    }
                }
            });
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
                $scheduledEnd = $this->scheduledDateTime($detail, (string) $detail->end_time);
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
        return $this->bookingHeadersQuery($filters)
            ->latest('created_at')
            ->paginate(12)
            ->withQueryString()
            ->through(fn (BookingHeader $header): array => $this->bookingRowPayload($header));
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
                        $builder->orWhereKey((int) $search);
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
    private function bookingRowPayload(BookingHeader $header): array
    {
        $canManageBookings = request()->user()?->hasPermission('bookings.manage') ?? false;

        $bookingSummaries = $header->details
            ->map(function (BookingDetail $detail) use ($header, $canManageBookings): array {
                $scheduledStart = $this->scheduledDateTime($detail, (string) $detail->start_time);
                $scheduledEnd = $this->scheduledDateTime($detail, (string) $detail->end_time);
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
                    'approve_url' => $canManageBookings ? route('admin.booking-details.approve', ['bookingDetail' => $detail->getKey()]) : null,
                    'reject_url' => $canManageBookings ? route('admin.booking-details.reject', ['bookingDetail' => $detail->getKey()]) : null,
                    'start_url' => $canManageBookings ? route('admin.booking-details.start', ['bookingDetail' => $detail->getKey()]) : null,
                    'end_url' => $canManageBookings ? route('admin.booking-details.end', ['bookingDetail' => $detail->getKey()]) : null,
                    'can_review' => $canManageBookings && ! in_array($detailStatus, ['confirmed', 'cancelled'], true),
                    'can_start' => $canManageBookings && $this->canStartDetail($detail),
                    'can_end' => $canManageBookings && $this->canEndDetail($detail),
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
            'booking_type' => ucfirst((string) $header->booking_type),
            'payment_method' => ucfirst(str_replace('_', ' ', (string) $header->payment_method)),
            'reference' => $header->reference_no,
            'booking_count' => $bookingSummaries->count(),
            'slot_count' => $bookingSummaries->count(),
            'latest_timestamp' => optional($header->created_at)?->timestamp ?? 0,
            'latest_date' => optional($header->created_at)->format('M j, Y'),
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
