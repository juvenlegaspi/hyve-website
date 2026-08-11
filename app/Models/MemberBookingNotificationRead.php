<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemberBookingNotificationRead extends Model
{
    use HasFactory;

    protected $fillable = ['booking_activity_id', 'user_id', 'read_at'];

    protected $casts = ['read_at' => 'datetime'];

    public function bookingActivity(): BelongsTo
    {
        return $this->belongsTo(BookingActivity::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
