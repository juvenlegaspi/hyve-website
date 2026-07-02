<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingWifiVoucher extends Model
{
    use HasFactory;

    public const STATUS_READY = 'ready';
    public const STATUS_REVOKED = 'revoked';
    public const STATUS_EXPIRED = 'expired';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'booking_header_id',
        'provider',
        'code',
        'username',
        'password',
        'status',
        'valid_from',
        'valid_until',
        'access_minutes',
        'sync_status',
        'mikrotik_user_ref',
        'last_synced_at',
        'last_sync_error',
        'revoked_at',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'valid_from' => 'datetime',
        'valid_until' => 'datetime',
        'access_minutes' => 'integer',
        'last_synced_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public function bookingHeader(): BelongsTo
    {
        return $this->belongsTo(BookingHeader::class);
    }
}
