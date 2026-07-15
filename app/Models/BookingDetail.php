<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BookingDetail extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_CANCELLED = 'cancelled';

    public const PROGRESS_SCHEDULED = 'scheduled';

    public const PROGRESS_READY = 'ready_to_start';

    public const PROGRESS_IN_PROGRESS = 'in_progress';

    public const PROGRESS_COMPLETED = 'completed';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'booking_header_id',
        'space_id',
        'hyve_room_id',
        'booking_date',
        'booking_end_date',
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
        'progress_status',
        'actual_start_at',
        'actual_end_at',
        'end_reminder_sent_at',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'booking_date' => 'date',
        'booking_end_date' => 'date',
        'duration_hours' => 'decimal:2',
        'billed_hours' => 'decimal:2',
        'guests' => 'integer',
        'rate_amount' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'actual_start_at' => 'datetime',
        'actual_end_at' => 'datetime',
        'end_reminder_sent_at' => 'datetime',
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

    public function activities(): HasMany
    {
        return $this->hasMany(BookingActivity::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(BookingPayment::class);
    }
}
