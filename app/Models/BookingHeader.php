<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BookingHeader extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const TYPE_GUEST = 'guest';
    public const TYPE_MEMBER = 'member';
    public const TYPE_MONTHLY = 'monthly';
    public const SOURCE_WEB = 'web';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'reference_no',
        'customer_name',
        'email',
        'phone',
        'booking_type',
        'source',
        'payment_method',
        'payment_status',
        'payment_proof_path',
        'payment_proof_name',
        'total_amount',
        'downpayment_amount',
        'balance_amount',
        'notes',
        'status',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'total_amount' => 'decimal:2',
        'downpayment_amount' => 'decimal:2',
        'balance_amount' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function details(): HasMany
    {
        return $this->hasMany(BookingDetail::class);
    }
}
