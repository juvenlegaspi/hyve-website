<?php

require __DIR__ . '/vendor/autoload.php';

$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$mode = $argv[1] ?? 'config';

if ($mode === 'config') {
    echo json_encode([
        'mail_mailer' => config('mail.default'),
        'mail_host' => config('mail.mailers.smtp.host'),
        'mail_port' => config('mail.mailers.smtp.port'),
        'mail_user' => config('mail.mailers.smtp.username'),
        'semaphore_key' => (bool) config('services.semaphore.api_key'),
        'semaphore_sender' => config('services.semaphore.sender_name'),
    ], JSON_PRETTY_PRINT);
    exit;
}

if ($mode === 'pending') {
    echo App\Models\BookingHeader::query()
        ->where('status', 'pending')
        ->latest('id')
        ->limit(5)
        ->get(['id', 'reference_no', 'customer_name', 'email', 'phone', 'status'])
        ->toJson(JSON_PRETTY_PRINT);
    exit;
}

if ($mode === 'send-test') {
    $bookingId = isset($argv[2]) ? (int) $argv[2] : 0;

    if ($bookingId < 1) {
        fwrite(STDERR, "Usage: php .tmp_notification_probe.php send-test <booking_header_id>\n");
        exit(1);
    }

    $bookingHeader = App\Models\BookingHeader::query()
        ->with(['details.hyveRoom', 'details.space'])
        ->find($bookingId);

    if (! $bookingHeader) {
        fwrite(STDERR, "Booking header not found.\n");
        exit(1);
    }

    $details = $bookingHeader->details
        ->whereIn('status', ['approved', 'confirmed', 'pending'])
        ->values();

    $lines = $details->map(function ($detail) {
        $room = $detail->hyveRoom?->name
            ?? $detail->space?->name
            ?? ('Room #' . $detail->hyve_room_id);

        $dateLabel = optional($detail->start_at)->format('M j, Y');
        $timeLabel = optional($detail->start_at)->format('g:i A') . ' - ' . optional($detail->end_at)->format('g:i A');

        return [
            'room_name' => $room,
            'date' => $dateLabel,
            'time' => $timeLabel,
        ];
    })->all();

    $context = [
        'customer_name' => $bookingHeader->customer_name,
        'reference_no' => $bookingHeader->reference_no,
        'email' => $bookingHeader->email,
        'phone' => $bookingHeader->phone,
        'payment_method' => ucfirst(str_replace('_', ' ', (string) $bookingHeader->payment_method)),
        'booking_type' => $bookingHeader->booking_type,
        'total_amount' => (float) $bookingHeader->total_amount,
        'downpayment_amount' => (float) $bookingHeader->downpayment_amount,
        'balance_amount' => (float) $bookingHeader->balance_amount,
        'booking_count' => count($lines),
        'lines' => $lines,
        'single_line' => count($lines) === 1 ? ($lines[0] ?? null) : null,
    ];

    echo "Testing booking header #{$bookingHeader->id} ({$bookingHeader->reference_no})\n";
    echo "Customer: {$bookingHeader->customer_name}\n";
    echo "Email: {$bookingHeader->email}\n";
    echo "Phone: {$bookingHeader->phone}\n\n";

    try {
        Illuminate\Support\Facades\Mail::to($bookingHeader->email)
            ->send(new App\Mail\BookingApprovedMail($context));
        echo "[OK] Email send call completed.\n";
    } catch (Throwable $e) {
        echo "[FAIL] Email error: {$e->getMessage()}\n";
    }

    try {
        app(App\Services\BookingApprovalTextService::class)
            ->send($bookingHeader->phone, $context);
        echo "[OK] SMS send call completed.\n";
    } catch (Throwable $e) {
        echo "[FAIL] SMS error: {$e->getMessage()}\n";
    }

    exit;
}

fwrite(STDERR, "Unknown mode: {$mode}\n");
exit(1);
