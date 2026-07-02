<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingPayment extends Model
{
    use HasFactory;

    public const TYPE_BALANCE = 'balance';
    public const TYPE_DOWNPAYMENT = 'downpayment';

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'booking_header_id',
        'booking_detail_id',
        'user_id',
        'payment_type',
        'amount',
        'payment_method',
        'status',
        'payment_proof_path',
        'payment_proof_name',
        'notes',
        'review_notes',
        'paid_at',
        'verified_at',
        'verified_by',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
        'verified_at' => 'datetime',
    ];

    public function bookingHeader(): BelongsTo
    {
        return $this->belongsTo(BookingHeader::class);
    }

    public function bookingDetail(): BelongsTo
    {
        return $this->belongsTo(BookingDetail::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function verifiedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
