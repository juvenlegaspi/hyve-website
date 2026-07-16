<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\BookingPaymentReceiptMail;
use App\Models\BookingDetail;
use App\Models\BookingHeader;
use App\Models\BookingPayment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AdminPaymentController extends Controller
{
    private const DISCOUNT_PRESETS = [
        'none' => ['label' => 'No discount', 'rate' => 0],
        'senior' => ['label' => 'Senior Citizen (20%)', 'rate' => 20],
        'pwd' => ['label' => 'PWD (20%)', 'rate' => 20],
        'promo_10' => ['label' => 'Promo (10%)', 'rate' => 10],
        'courtesy_15' => ['label' => 'Courtesy (15%)', 'rate' => 15],
    ];

    public function index(Request $request): View
    {
        $filters = [
            'search' => trim((string) $request->query('search', '')),
            'status' => (string) $request->query('status', 'all'),
            'method' => (string) $request->query('method', 'all'),
        ];

        $bookings = BookingHeader::query()
            ->with([
                'details.hyveRoom',
                'details.space',
                'user',
                'payments.bookingDetail.hyveRoom',
                'payments.bookingDetail.space',
                'payments.user',
                'payments.verifiedByUser',
            ])
            ->where('status', 'confirmed')
            ->when($filters['search'] !== '', function ($query) use ($filters) {
                $search = $filters['search'];

                $query->where(function ($builder) use ($search) {
                    $builder
                        ->where('customer_name', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%')
                        ->orWhere('reference_no', 'like', '%'.$search.'%')
                        ->orWhere('phone', 'like', '%'.$search.'%')
                        ->orWhereHas('user', function ($userQuery) use ($search) {
                            $userQuery
                                ->where('first_name', 'like', '%'.$search.'%')
                                ->orWhere('last_name', 'like', '%'.$search.'%')
                                ->orWhere('email', 'like', '%'.$search.'%');
                        });

                    if (ctype_digit($search)) {
                        $builder->orWhereKey((int) $search);
                    }
                });
            })
            ->when($filters['status'] !== 'all', function ($query) use ($filters) {
                $query->whereHas('payments', fn ($paymentQuery) => $paymentQuery->where('status', $filters['status']));
            })
            ->when($filters['method'] !== 'all', function ($query) use ($filters) {
                $query->whereHas('payments', fn ($paymentQuery) => $paymentQuery->where('payment_method', $filters['method']));
            })
            ->orderByRaw("(select count(*) from booking_payments where booking_payments.booking_header_id = booking_headers.id and booking_payments.status = 'pending') desc")
            ->latest('updated_at')
            ->paginate(12)
            ->withQueryString();

        $paymentSummary = [
            'pending_count' => BookingPayment::query()
                ->where('status', BookingPayment::STATUS_PENDING)
                ->whereHas('bookingHeader', fn ($query) => $query->where('status', 'confirmed'))
                ->count(),
            'pending_amount' => (float) BookingPayment::query()
                ->where('status', BookingPayment::STATUS_PENDING)
                ->whereHas('bookingHeader', fn ($query) => $query->where('status', 'confirmed'))
                ->sum('amount'),
            'approved_today_amount' => (float) BookingPayment::query()
                ->where('status', BookingPayment::STATUS_APPROVED)
                ->whereHas('bookingHeader', fn ($query) => $query->where('status', 'confirmed'))
                ->whereDate('verified_at', today())
                ->sum('amount'),
            'members_with_balance' => BookingHeader::query()
                ->where('status', 'confirmed')
                ->where('booking_type', BookingHeader::TYPE_MEMBER)
                ->where('balance_amount', '>', 0)
                ->distinct('user_id')
                ->count('user_id'),
        ];

        return view('admin.payments.index', [
            'meta' => [
                'title' => 'Payments | HYVE Admin',
                'description' => 'Review member payment submissions, outstanding balances, and payment verification tasks.',
            ],
            'adminUser' => $request->user(),
            'filters' => $filters,
            'bookings' => $bookings,
            'bookingRows' => $bookings->getCollection()->map(fn (BookingHeader $header): array => $this->bookingRowPayload($header)),
            'discountOptions' => $this->discountOptions(),
            'paymentSummary' => $paymentSummary,
        ]);
    }

    public function proof(BookingPayment $bookingPayment)
    {
        $path = (string) ($bookingPayment->payment_proof_path ?? '');

        abort_if($path === '', 404);
        abort_unless(Storage::disk('public')->exists($path), 404);

        $filePath = Storage::disk('public')->path($path);
        $mimeType = Storage::disk('public')->mimeType($path) ?: 'application/octet-stream';

        return response()->file($filePath, [
            'Content-Type' => $mimeType,
            'Content-Disposition' => 'inline; filename="'.basename($path).'"',
        ]);
    }

    public function receipt(BookingPayment $bookingPayment): View
    {
        abort_unless((string) $bookingPayment->status === BookingPayment::STATUS_APPROVED, 404);

        $bookingPayment->loadMissing([
            'bookingHeader.details.hyveRoom',
            'bookingHeader.details.space',
            'bookingHeader.user',
            'bookingDetail.hyveRoom',
            'bookingDetail.space',
            'user',
            'verifiedByUser',
        ]);

        $header = $bookingPayment->bookingHeader;
        abort_unless($header, 404);

        $detail = $bookingPayment->bookingDetail;
        $details = $header->details
            ->sortBy([
                ['booking_date', 'asc'],
                ['start_time', 'asc'],
            ])
            ->values();

        return view('admin.payments.receipt', [
            'payment' => $bookingPayment,
            'header' => $header,
            'detail' => $detail,
            'details' => $details,
            'roomName' => $detail?->hyveRoom?->room_name
                ?? $detail?->space?->name
                ?? ($details->first()?->hyveRoom?->room_name ?? $details->first()?->space?->name ?? 'HYVE Workspace'),
        ]);
    }

    public function approve(Request $request, BookingPayment $bookingPayment): RedirectResponse
    {
        if ((string) $bookingPayment->status !== BookingPayment::STATUS_PENDING) {
            return back()->with('admin_error', 'This payment has already been reviewed.');
        }

        $shouldSendReceiptEmail = false;

        DB::transaction(function () use ($request, $bookingPayment, &$shouldSendReceiptEmail): void {
            $payment = BookingPayment::query()->lockForUpdate()->findOrFail($bookingPayment->getKey());
            $header = BookingHeader::query()->lockForUpdate()->findOrFail($payment->booking_header_id);
            $wasFullyPaid = (string) ($header->payment_status ?? '') === 'paid';

            $payment->update([
                'status' => BookingPayment::STATUS_APPROVED,
                'review_notes' => trim((string) $request->input('review_notes', '')) ?: $payment->review_notes,
                'verified_at' => now(),
                'verified_by' => $request->user()?->id,
            ]);

            $this->syncHeaderPaymentSnapshot($header->fresh('payments'));

            $freshHeader = $header->fresh();
            $shouldSendReceiptEmail = ! $wasFullyPaid
                && $freshHeader
                && (string) ($freshHeader->payment_status ?? '') === 'paid'
                && trim((string) ($freshHeader->email ?? '')) !== '';
        });

        if ($shouldSendReceiptEmail) {
            $this->sendPaymentReceiptEmail($bookingPayment->bookingHeader()->first()?->load(['details.hyveRoom', 'details.space', 'payments']));
        }

        return back()
            ->with('admin_success', 'Payment verified and booking balance updated.')
            ->with('admin_trigger_bookings_refresh', $bookingPayment->booking_header_id)
            ->with('admin_open_payment_modal', $bookingPayment->booking_header_id);
    }

    public function reject(Request $request, BookingPayment $bookingPayment): RedirectResponse
    {
        if ((string) $bookingPayment->status !== BookingPayment::STATUS_PENDING) {
            return back()->with('admin_error', 'This payment has already been reviewed.');
        }

        DB::transaction(function () use ($request, $bookingPayment): void {
            $payment = BookingPayment::query()->lockForUpdate()->findOrFail($bookingPayment->getKey());
            $header = BookingHeader::query()->lockForUpdate()->findOrFail($payment->booking_header_id);

            $payment->update([
                'status' => BookingPayment::STATUS_REJECTED,
                'review_notes' => trim((string) $request->input('review_notes', '')) ?: $payment->review_notes,
                'verified_at' => now(),
                'verified_by' => $request->user()?->id,
            ]);

            $this->syncHeaderPaymentSnapshot($header->fresh('payments'));
        });

        return back()
            ->with('admin_success', 'Payment rejected.')
            ->with('admin_trigger_bookings_refresh', $bookingPayment->booking_header_id)
            ->with('admin_open_payment_modal', $bookingPayment->booking_header_id);
    }

    public function applyDiscount(Request $request, BookingHeader $bookingHeader): RedirectResponse
    {
        $validated = $request->validate([
            'discount_code' => ['required', Rule::in(array_keys(self::DISCOUNT_PRESETS))],
        ]);

        if ((string) $bookingHeader->status !== 'confirmed') {
            return back()
                ->with('admin_error', 'Only approved bookings can be discounted here.')
                ->with('admin_open_payment_modal', $bookingHeader->getKey());
        }

        DB::transaction(function () use ($bookingHeader, $validated): void {
            $header = BookingHeader::query()->lockForUpdate()->findOrFail($bookingHeader->getKey());
            $approvedTotal = round(
                (float) BookingPayment::query()
                    ->where('booking_header_id', $header->getKey())
                    ->where('status', BookingPayment::STATUS_APPROVED)
                    ->sum('amount'),
                2
            );

            $discountMeta = $this->discountMeta((string) $validated['discount_code']);
            $header->update($this->discountColumnPayload($header, $discountMeta['code']));

            $this->syncHeaderPaymentSnapshot($header->fresh('payments'));

            if ($approvedTotal > 0) {
                $freshHeader = $header->fresh();

                if ($freshHeader && (float) ($freshHeader->balance_amount ?? 0) <= 0) {
                    $freshHeader->update([
                        'payment_status' => 'paid',
                    ]);
                }
            }
        });

        $discountMeta = $this->discountMeta((string) $validated['discount_code']);

        return back()
            ->with('admin_success', $discountMeta['rate'] > 0 ? $discountMeta['label'].' applied successfully.' : 'Discount removed successfully.')
            ->with('admin_open_payment_modal', $bookingHeader->getKey());
    }

    public function record(Request $request, BookingHeader $bookingHeader): RedirectResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0'],
            'payment_method' => ['required', Rule::in(['gcash', 'bank_transfer', 'cash'])],
            'discount_code' => ['nullable', Rule::in(array_keys(self::DISCOUNT_PRESETS))],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        if ((string) $bookingHeader->status !== 'confirmed') {
            return back()
                ->with('admin_error', 'Only approved bookings can receive payments here.')
                ->with('admin_open_payment_modal', $bookingHeader->getKey());
        }

        $currentBalance = round((float) ($bookingHeader->balance_amount ?? 0), 2);
        $amount = round((float) $validated['amount'], 2);

        if ($amount > $currentBalance) {
            return back()
                ->with('admin_error', 'The recorded payment cannot be greater than the remaining balance.')
                ->with('admin_open_payment_modal', $bookingHeader->getKey());
        }

        $shouldSendReceiptEmail = false;

        DB::transaction(function () use ($request, $bookingHeader, $validated, $amount, &$shouldSendReceiptEmail): void {
            $header = BookingHeader::query()->lockForUpdate()->findOrFail($bookingHeader->getKey());
            $discountCode = (string) ($validated['discount_code'] ?? $header->discount_code ?? 'none');

            $header->update($this->discountColumnPayload($header, $discountCode));
            $header->refresh();

            $freshBalance = round((float) ($header->balance_amount ?? 0), 2);

            if ($amount > $freshBalance) {
                throw ValidationException::withMessages([
                    'amount' => 'The recorded payment cannot be greater than the remaining balance after discount.',
                ]);
            }

            BookingPayment::query()->create([
                'booking_header_id' => $header->getKey(),
                'user_id' => $header->user_id,
                'payment_type' => BookingPayment::TYPE_BALANCE,
                'amount' => $amount,
                'payment_method' => $validated['payment_method'],
                'status' => BookingPayment::STATUS_APPROVED,
                'notes' => trim((string) ($validated['notes'] ?? '')) ?: 'Payment recorded manually by admin.',
                'paid_at' => now(),
                'verified_at' => now(),
                'verified_by' => $request->user()?->id,
            ]);

            $this->syncHeaderPaymentSnapshot($header->fresh('payments'));

            $freshHeader = $header->fresh('payments');
            $shouldSendReceiptEmail = $freshHeader
                && (string) ($freshHeader->payment_status ?? '') === 'paid'
                && trim((string) ($freshHeader->email ?? '')) !== '';
        });

        if ($shouldSendReceiptEmail) {
            $this->sendPaymentReceiptEmail($bookingHeader->fresh(['details.hyveRoom', 'details.space', 'payments']));
        }

        return back()
            ->with('admin_success', 'Payment recorded successfully.')
            ->with('admin_trigger_bookings_refresh', $bookingHeader->getKey())
            ->with('admin_open_payment_modal', $bookingHeader->getKey());
    }

    private function resolveHeaderPaymentStatus(BookingHeader $header, float $approvedTotal, ?float $nextBalance = null): string
    {
        if ((string) $header->status === 'cancelled') {
            return 'rejected';
        }

        $balance = round((float) ($nextBalance ?? $header->balance_amount ?? 0), 2);

        if ($balance <= 0) {
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

    /**
     * @return array<int, array{value: string, label: string, rate: float}>
     */
    private function discountOptions(): array
    {
        return collect(self::DISCOUNT_PRESETS)
            ->map(fn (array $meta, string $code): array => [
                'value' => $code,
                'label' => (string) $meta['label'],
                'rate' => (float) $meta['rate'],
            ])
            ->values()
            ->all();
    }

    /**
     * @return array{code: string, label: string, rate: float}
     */
    private function discountMeta(?string $code): array
    {
        $normalizedCode = array_key_exists((string) $code, self::DISCOUNT_PRESETS)
            ? (string) $code
            : 'none';
        $meta = self::DISCOUNT_PRESETS[$normalizedCode];

        return [
            'code' => $normalizedCode,
            'label' => (string) $meta['label'],
            'rate' => (float) $meta['rate'],
        ];
    }

    /**
     * @return array{discount_code: ?string, discount_label: ?string, discount_rate: ?float, discount_amount: float, discounted_total_amount: float}
     */
    private function discountColumnPayload(BookingHeader $header, ?string $code): array
    {
        $discountMeta = $this->discountMeta($code);
        $grossTotal = round((float) ($header->total_amount ?? 0), 2);
        $rate = $discountMeta['rate'];
        $discountAmount = $rate > 0 ? round($grossTotal * ($rate / 100), 2) : 0.0;
        $discountedTotal = round(max(0, $grossTotal - $discountAmount), 2);

        return [
            'discount_code' => $rate > 0 ? $discountMeta['code'] : null,
            'discount_label' => $rate > 0 ? $discountMeta['label'] : null,
            'discount_rate' => $rate > 0 ? $rate : null,
            'discount_amount' => $discountAmount,
            'discounted_total_amount' => $discountedTotal,
        ];
    }

    private function hasPendingPayments(int $bookingHeaderId, ?int $exceptPaymentId = null): bool
    {
        return BookingPayment::query()
            ->where('booking_header_id', $bookingHeaderId)
            ->where('status', BookingPayment::STATUS_PENDING)
            ->when($exceptPaymentId, fn ($query) => $query->whereKeyNot($exceptPaymentId))
            ->exists();
    }

    private function syncHeaderPaymentSnapshot(BookingHeader $header): void
    {
        $header->loadMissing('payments');

        $discountColumns = $this->discountColumnPayload($header, (string) ($header->discount_code ?? 'none'));
        if (
            (float) ($header->discount_amount ?? 0) !== (float) $discountColumns['discount_amount']
            || (float) ($header->discounted_total_amount ?? 0) !== (float) $discountColumns['discounted_total_amount']
            || (string) ($header->discount_code ?? '') !== (string) ($discountColumns['discount_code'] ?? '')
        ) {
            $header->forceFill($discountColumns);
        }

        $approvedTotal = round(
            (float) $header->payments
                ->where('status', BookingPayment::STATUS_APPROVED)
                ->sum(fn (BookingPayment $payment): float => (float) ($payment->amount ?? 0)),
            2
        );

        $effectiveTotal = round((float) ($header->discounted_total_amount ?? $header->total_amount ?? 0), 2);
        $nextBalance = round(max(0, $effectiveTotal - $approvedTotal), 2);

        $latestApprovedProof = $header->payments
            ->where('status', BookingPayment::STATUS_APPROVED)
            ->sortByDesc(fn (BookingPayment $payment) => optional($payment->verified_at)->timestamp ?? optional($payment->paid_at)->timestamp ?? 0)
            ->first();

        $header->update([
            ...$discountColumns,
            'payment_method' => $latestApprovedProof?->payment_method ?? $header->payment_method,
            'payment_status' => $this->resolveHeaderPaymentStatus($header, $approvedTotal, $nextBalance),
            'payment_proof_path' => $latestApprovedProof?->payment_proof_path ?? $header->payment_proof_path,
            'payment_proof_name' => $latestApprovedProof?->payment_proof_name ?? $header->payment_proof_name,
            'downpayment_amount' => $approvedTotal,
            'balance_amount' => $nextBalance,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function bookingRowPayload(BookingHeader $header): array
    {
        $canManagePayments = request()->user()?->hasPermission('payments.manage') ?? false;

        $details = $header->details
            ->sortBy([
                ['booking_date', 'asc'],
                ['start_time', 'asc'],
            ])
            ->values();

        $payments = $header->payments
            ->sortByDesc(fn (BookingPayment $payment) => optional($payment->paid_at)->timestamp ?? optional($payment->created_at)->timestamp ?? 0)
            ->values();

        $pendingCount = $payments->where('status', BookingPayment::STATUS_PENDING)->count();
        $approvedTotal = (float) $payments->where('status', BookingPayment::STATUS_APPROVED)->sum('amount');
        $latestPayment = $payments->first();
        $latestApprovedPayment = $payments
            ->where('status', BookingPayment::STATUS_APPROVED)
            ->sortByDesc(fn (BookingPayment $payment) => optional($payment->verified_at)->timestamp ?? optional($payment->paid_at)->timestamp ?? 0)
            ->first();
        $previewRooms = $details
            ->map(fn ($detail) => $detail->hyveRoom?->room_name ?? $detail->space?->name ?? 'Room')
            ->filter()
            ->unique()
            ->take(3)
            ->values()
            ->all();

        return [
            'id' => $header->getKey(),
            'reference' => (string) $header->reference_no,
            'customer_name' => (string) $header->customer_name,
            'email' => (string) $header->email,
            'phone' => (string) $header->phone,
            'booking_type' => ucfirst((string) $header->booking_type),
            'payment_method' => ucfirst(str_replace('_', ' ', (string) ($header->payment_method ?? 'direct_payment'))),
            'payment_status' => $this->paymentStatusLabel((string) ($header->payment_status ?? 'pending_verification')),
            'payment_status_key' => (string) ($header->payment_status ?? 'pending_verification'),
            'payment_status_class' => $this->paymentStatusClass((string) ($header->payment_status ?? 'pending_verification')),
            'gross_total_amount' => 'Php '.number_format((float) ($header->total_amount ?? 0), 2),
            'discount_label' => (string) ($header->discount_label ?? 'No discount'),
            'discount_rate' => round((float) ($header->discount_rate ?? 0), 2),
            'discount_amount' => 'Php '.number_format((float) ($header->discount_amount ?? 0), 2),
            'payable_total_amount' => 'Php '.number_format((float) ($header->discounted_total_amount ?? $header->total_amount ?? 0), 2),
            'discount_code' => (string) ($header->discount_code ?? 'none'),
            'downpayment_amount' => 'Php '.number_format((float) ($header->downpayment_amount ?? 0), 2),
            'balance_amount' => 'Php '.number_format((float) ($header->balance_amount ?? 0), 2),
            'booking_count' => $details->count(),
            'latest_date' => optional($header->created_at)->format('M j, Y'),
            'pending_count' => $pendingCount,
            'approved_total' => 'Php '.number_format($approvedTotal, 2),
            'latest_payment_label' => $latestPayment
                ? ucfirst((string) $latestPayment->status).' - Php '.number_format((float) $latestPayment->amount, 2)
                : 'No payment submission yet',
            'receipt_url' => (string) ($header->payment_status ?? '') === 'paid' && $latestApprovedPayment
                ? route('admin.payments.receipt', $latestApprovedPayment)
                : null,
            'preview_rooms' => $previewRooms,
            'payments' => $payments->map(function (BookingPayment $payment) use ($canManagePayments): array {
                $detail = $payment->bookingDetail;
                $roomName = $detail?->hyveRoom?->room_name ?? $detail?->space?->name ?? 'Booking payment';

                return [
                    'id' => $payment->getKey(),
                    'amount' => 'Php '.number_format((float) $payment->amount, 2),
                    'payment_method' => ucfirst(str_replace('_', ' ', (string) $payment->payment_method)),
                    'payment_type' => ucfirst(str_replace('_', ' ', (string) $payment->payment_type)),
                    'status' => ucfirst((string) $payment->status),
                    'status_class' => $this->paymentStatusClass((string) $payment->status),
                    'room_name' => $roomName,
                    'date' => optional($detail?->booking_date)->format('F j, Y') ?? '--',
                    'time' => $detail
                        ? $this->displayTime((string) $detail->start_time).' - '.$this->displayTime((string) $detail->end_time)
                        : '--',
                    'notes' => trim((string) ($payment->notes ?? '')) ?: '--',
                    'review_notes' => trim((string) ($payment->review_notes ?? '')) ?: '--',
                    'submitted_at' => optional($payment->paid_at)->format('M j, Y g:i A') ?? optional($payment->created_at)->format('M j, Y g:i A') ?? '--',
                    'verified_at' => optional($payment->verified_at)->format('M j, Y g:i A') ?? '--',
                    'verified_by' => $payment->verifiedByUser?->name ?? 'Admin',
                    'proof_url' => $payment->payment_proof_path ? route('admin.payments.proof', $payment) : null,
                    'approve_url' => $canManagePayments ? route('admin.payments.approve', $payment) : null,
                    'reject_url' => $canManagePayments ? route('admin.payments.reject', $payment) : null,
                    'verify_label' => 'Verify Payment',
                    'can_review' => $canManagePayments && (string) $payment->status === BookingPayment::STATUS_PENDING,
                ];
            })->all(),
            'discount_apply_url' => $canManagePayments ? route('admin.payments.discount', $header) : null,
            'record_payment_url' => $canManagePayments ? route('admin.payments.record', $header) : null,
            'can_record_payment' => $canManagePayments,
        ];
    }

    private function paymentStatusClass(string $status): string
    {
        return match ($status) {
            'approved', 'paid' => 'admin-bookings-badge--paid',
            'partially_paid' => 'admin-bookings-badge--partial',
            'rejected', 'cancelled' => 'admin-bookings-badge--rejected',
            default => 'admin-bookings-badge--pending',
        };
    }

    private function paymentStatusLabel(string $status): string
    {
        return match ($status) {
            'paid', 'approved' => 'Fully Paid',
            'partially_paid' => 'Partially Paid',
            'pending_balance_verification' => 'Payment Submitted',
            'rejected', 'cancelled' => 'Payment Rejected',
            default => 'Waiting Payment',
        };
    }

    private function displayTime(string $value): string
    {
        $format = strlen($value) === 5 ? 'H:i' : 'H:i:s';

        return Carbon::createFromFormat($format, $value)->format('g:i A');
    }

    private function sendPaymentReceiptEmail(?BookingHeader $header): void
    {
        if (! $header) {
            return;
        }

        $email = trim((string) ($header->email ?? ''));
        if ($email === '') {
            return;
        }

        $context = $this->paymentReceiptContext($header);

        try {
            Mail::to($email)->send(new BookingPaymentReceiptMail($context));
        } catch (\Throwable $exception) {
            Log::warning('Failed to send payment receipt email.', [
                'reference_no' => $header->reference_no,
                'email' => $email,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function paymentReceiptContext(BookingHeader $header): array
    {
        $header->loadMissing(['details.hyveRoom', 'details.space', 'payments']);

        $details = $header->details
            ->where('status', '!=', BookingDetail::STATUS_CANCELLED)
            ->sortBy([
                ['booking_date', 'asc'],
                ['start_time', 'asc'],
            ])
            ->values();

        $latestApprovedPayment = $header->payments
            ->where('status', BookingPayment::STATUS_APPROVED)
            ->sortByDesc(fn (BookingPayment $payment) => optional($payment->verified_at)->timestamp ?? optional($payment->paid_at)->timestamp ?? 0)
            ->first();

        $approvedPayments = $header->payments
            ->where('status', BookingPayment::STATUS_APPROVED)
            ->sortBy(fn (BookingPayment $payment) => optional($payment->verified_at)->timestamp ?? optional($payment->paid_at)->timestamp ?? optional($payment->created_at)->timestamp ?? 0)
            ->values();

        $paymentLines = $approvedPayments
            ->map(function (BookingPayment $payment) use ($latestApprovedPayment): array {
                $isLatestPayment = $latestApprovedPayment?->is($payment) ?? false;
                $label = match (true) {
                    (string) $payment->payment_type === BookingPayment::TYPE_DOWNPAYMENT => 'Downpayment',
                    $isLatestPayment => 'Final payment',
                    default => 'Balance payment',
                };

                return [
                    'label' => $label,
                    'method' => ucfirst(str_replace('_', ' ', (string) ($payment->payment_method ?? 'cash'))),
                    'paid_at' => optional($payment->verified_at ?? $payment->paid_at ?? $payment->created_at)->format('F j, Y g:i A') ?? '--',
                    'amount' => round((float) ($payment->amount ?? 0), 2),
                ];
            })
            ->all();

        $totalPaidAmount = round(
            (float) $approvedPayments->sum(fn (BookingPayment $payment): float => (float) ($payment->amount ?? 0)),
            2,
        );

        $lines = $details->map(function (BookingDetail $detail): array {
            $roomName = $detail->hyveRoom?->room_name ?? $detail->space?->name ?? 'Room';
            $startTime = $detail->start_time ? $this->displayTime((string) $detail->start_time) : '--';
            $endTime = $detail->end_time ? $this->displayTime((string) $detail->end_time) : '--';

            return [
                'room_name' => $roomName,
                'date' => optional($detail->booking_date)->format('F j, Y') ?? '--',
                'time' => $startTime.' - '.$endTime,
            ];
        })->all();

        return [
            'customer_name' => (string) $header->customer_name,
            'reference_no' => (string) $header->reference_no,
            'payment_method' => ucfirst(str_replace('_', ' ', (string) ($latestApprovedPayment?->payment_method ?? $header->payment_method ?? 'cash'))),
            'paid_at' => optional($latestApprovedPayment?->verified_at ?? $latestApprovedPayment?->paid_at ?? now())->format('F j, Y g:i A'),
            'payment_amount' => round((float) ($latestApprovedPayment?->amount ?? 0), 2),
            'gross_total_amount' => round((float) ($header->total_amount ?? 0), 2),
            'discount_label' => (string) ($header->discount_label ?? 'No discount'),
            'discount_amount' => round((float) ($header->discount_amount ?? 0), 2),
            'payable_total_amount' => round((float) ($header->discounted_total_amount ?? $header->total_amount ?? 0), 2),
            'downpayment_amount' => round((float) ($header->downpayment_amount ?? 0), 2),
            'total_paid_amount' => $totalPaidAmount,
            'balance_amount' => round((float) ($header->balance_amount ?? 0), 2),
            'payment_lines' => $paymentLines,
            'lines' => $lines,
        ];
    }
}
