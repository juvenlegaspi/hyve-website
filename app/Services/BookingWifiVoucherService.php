<?php

namespace App\Services;

use App\Models\BookingDetail;
use App\Models\BookingHeader;
use App\Models\BookingWifiVoucher;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class BookingWifiVoucherService
{
    public function ensureVoucherForBooking(BookingHeader $bookingHeader): ?BookingWifiVoucher
    {
        $bookingHeader->loadMissing('details');

        if ((string) $bookingHeader->status !== 'confirmed') {
            return null;
        }

        $window = $this->bookingWindow($bookingHeader);

        if (! $window) {
            return null;
        }

        $voucher = $bookingHeader->wifiVoucher()->first();

        if ($voucher && (string) $voucher->status === BookingWifiVoucher::STATUS_REVOKED) {
            $voucher->status = BookingWifiVoucher::STATUS_READY;
            $voucher->revoked_at = null;
        }

        $voucher ??= new BookingWifiVoucher([
            'provider' => 'mikrotik',
            'code' => $this->generateUniqueCode(),
            'username' => null,
            'password' => null,
            'status' => BookingWifiVoucher::STATUS_READY,
            'sync_status' => 'pending_device',
        ]);

        $voucher->booking_header_id = $bookingHeader->getKey();
        $voucher->valid_from = $window['start'];
        $voucher->valid_until = $window['end'];
        $voucher->access_minutes = $window['minutes'];
        $voucher->username = $voucher->code;
        $voucher->password = $voucher->code;
        $voucher->sync_status = 'pending_device';
        $voucher->last_sync_error = null;

        if ($window['end']->lte(now())) {
            $voucher->status = BookingWifiVoucher::STATUS_EXPIRED;
        } elseif ((string) $voucher->status !== BookingWifiVoucher::STATUS_REVOKED) {
            $voucher->status = BookingWifiVoucher::STATUS_READY;
        }

        $voucher->save();

        return $voucher->fresh();
    }

    public function revokeVoucherForBooking(BookingHeader $bookingHeader): void
    {
        $voucher = $bookingHeader->wifiVoucher;

        if (! $voucher) {
            return;
        }

        $voucher->update([
            'status' => BookingWifiVoucher::STATUS_REVOKED,
            'revoked_at' => now(),
            'sync_status' => 'pending_device',
        ]);
    }

    /**
     * @return array{code:string,status:string,status_label:string,provider:string,username:string,password:string,valid_from:string,valid_until:string,access_window:string,access_minutes:int,sync_status:string}|null
     */
    public function payloadForBooking(?BookingHeader $bookingHeader): ?array
    {
        $voucher = $bookingHeader?->wifiVoucher;

        if (! $voucher) {
            return null;
        }

        $status = $this->resolvedStatus($voucher);

        return [
            'code' => (string) $voucher->code,
            'status' => $status,
            'status_label' => $this->statusLabel($status),
            'provider' => strtoupper((string) ($voucher->provider ?? 'mikrotik')),
            'username' => (string) ($voucher->username ?: $voucher->code),
            'password' => (string) ($voucher->password ?: $voucher->code),
            'valid_from' => optional($voucher->valid_from)->format('F j, Y g:i A') ?? '--',
            'valid_until' => optional($voucher->valid_until)->format('F j, Y g:i A') ?? '--',
            'access_window' => $this->accessWindowLabel($voucher),
            'access_minutes' => (int) ($voucher->access_minutes ?? 0),
            'sync_status' => $this->syncStatusLabel((string) ($voucher->sync_status ?? 'pending_device')),
        ];
    }

    private function generateUniqueCode(): string
    {
        do {
            $code = 'HYVE-WIFI-'.Str::upper(Str::random(8));
        } while (BookingWifiVoucher::query()->where('code', $code)->exists());

        return $code;
    }

    /**
     * @return array{start:Carbon,end:Carbon,minutes:int}|null
     */
    private function bookingWindow(BookingHeader $bookingHeader): ?array
    {
        $details = $bookingHeader->details
            ->filter(fn (BookingDetail $detail): bool => (string) $detail->status !== BookingDetail::STATUS_CANCELLED && $detail->booking_date)
            ->sortBy([
                ['booking_date', 'asc'],
                ['start_time', 'asc'],
            ])
            ->values();

        if ($details->isEmpty()) {
            return null;
        }

        $firstDetail = $details->first();
        $lastDetail = $details->sortBy([
            ['booking_date', 'desc'],
            ['end_time', 'desc'],
        ])->first();

        if (! $firstDetail || ! $lastDetail) {
            return null;
        }

        $start = Carbon::parse($firstDetail->booking_date->format('Y-m-d').' '.$firstDetail->start_time);
        $endDate = $lastDetail->booking_end_date ?: $lastDetail->booking_date;
        $end = Carbon::parse($endDate->format('Y-m-d').' '.$lastDetail->end_time);

        if ($end->lte($start)) {
            $end->addDay();
        }

        return [
            'start' => $start,
            'end' => $end,
            'minutes' => $start->diffInMinutes($end, true),
        ];
    }

    private function resolvedStatus(BookingWifiVoucher $voucher): string
    {
        $status = (string) ($voucher->status ?? BookingWifiVoucher::STATUS_READY);

        if ($status === BookingWifiVoucher::STATUS_REVOKED) {
            return $status;
        }

        if ($voucher->valid_until && $voucher->valid_until->lte(now())) {
            return BookingWifiVoucher::STATUS_EXPIRED;
        }

        return $status;
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            BookingWifiVoucher::STATUS_REVOKED => 'Revoked',
            BookingWifiVoucher::STATUS_EXPIRED => 'Expired',
            default => 'Ready',
        };
    }

    private function syncStatusLabel(string $status): string
    {
        return match ($status) {
            'synced' => 'Synced to device',
            'sync_failed' => 'Sync failed',
            default => 'Waiting for MikroTik device',
        };
    }

    private function accessWindowLabel(BookingWifiVoucher $voucher): string
    {
        $minutes = (int) ($voucher->access_minutes ?? 0);

        if ($minutes <= 0) {
            return '--';
        }

        $hours = intdiv($minutes, 60);
        $remainingMinutes = $minutes % 60;
        $parts = [];

        if ($hours > 0) {
            $parts[] = $hours.' '.Str::plural('hour', $hours);
        }

        if ($remainingMinutes > 0) {
            $parts[] = $remainingMinutes.' mins';
        }

        return implode(' ', $parts);
    }
}
