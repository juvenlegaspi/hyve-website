<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingActivity extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_header_id',
        'booking_detail_id',
        'actor_user_id',
        'event_key',
        'event_label',
        'reference_no',
        'customer_name',
        'room_name',
        'booking_date',
        'time_range',
        'message',
        'read_at',
    ];

    protected $casts = [
        'booking_date' => 'date',
        'read_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function bookingHeader(): BelongsTo
    {
        return $this->belongsTo(BookingHeader::class);
    }

    public function bookingDetail(): BelongsTo
    {
        return $this->belongsTo(BookingDetail::class);
    }

    public function actorUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
