<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class BookingHeader extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const TYPE_GUEST = 'guest';
    public const TYPE_MEMBER = 'member';
    public const TYPE_MONTHLY = 'monthly';
    public const SOURCE_WEB = 'web';
    public const SOURCE_ADMIN = 'admin';

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
        'discount_code',
        'discount_label',
        'discount_rate',
        'discount_amount',
        'discounted_total_amount',
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
        'discount_rate' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'discounted_total_amount' => 'decimal:2',
        'downpayment_amount' => 'decimal:2',
        'balance_amount' => 'decimal:2',
    ];

    public function grossTotalAmount(): float
    {
        return round((float) ($this->total_amount ?? 0), 2);
    }

    public function effectiveTotalAmount(): float
    {
        $storedDiscountedTotal = $this->discounted_total_amount;

        if ($storedDiscountedTotal !== null) {
            return round((float) $storedDiscountedTotal, 2);
        }

        return round(max(0, $this->grossTotalAmount() - (float) ($this->discount_amount ?? 0)), 2);
    }

    /**
     * @return array{discount_amount: float, discounted_total_amount: float}
     */
    public function discountSnapshotFor(float $grossTotal): array
    {
        $grossTotal = round(max(0, $grossTotal), 2);
        $rate = round(max(0, (float) ($this->discount_rate ?? 0)), 2);
        $discountAmount = $rate > 0 ? round($grossTotal * ($rate / 100), 2) : 0.0;

        return [
            'discount_amount' => $discountAmount,
            'discounted_total_amount' => round(max(0, $grossTotal - $discountAmount), 2),
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function details(): HasMany
    {
        return $this->hasMany(BookingDetail::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(BookingActivity::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(BookingPayment::class);
    }

    public function wifiVoucher(): HasOne
    {
        return $this->hasOne(BookingWifiVoucher::class);
    }
}
