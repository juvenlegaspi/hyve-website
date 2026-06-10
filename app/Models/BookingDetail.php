<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingDetail extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'booking_header_id',
        'space_id',
        'hyve_room_id',
        'booking_date',
        'start_time',
        'end_time',
        'charge_period',
        'duration_hours',
        'billed_hours',
        'guests',
        'rate_name',
        'rate_amount',
        'subtotal',
        'status',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'booking_date' => 'date',
        'duration_hours' => 'decimal:2',
        'billed_hours' => 'decimal:2',
        'guests' => 'integer',
        'rate_amount' => 'decimal:2',
        'subtotal' => 'decimal:2',
    ];

    public function bookingHeader(): BelongsTo
    {
        return $this->belongsTo(BookingHeader::class);
    }

    public function space(): BelongsTo
    {
        return $this->belongsTo(Space::class);
    }

    public function hyveRoom(): BelongsTo
    {
        return $this->belongsTo(HyveRoom::class);
    }
}
