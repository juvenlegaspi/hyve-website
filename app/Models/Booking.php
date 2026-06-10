<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'full_name',
        'email',
        'phone',
        'space_type',
        'booking_date',
        'start_time',
        'end_time',
        'guests',
        'notes',
        'status',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
