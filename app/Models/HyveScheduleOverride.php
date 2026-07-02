<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HyveScheduleOverride extends Model
{
    use HasFactory;

    public const MODE_DEFAULT = 'default';
    public const MODE_CUSTOM = 'custom';
    public const MODE_CLOSED = 'closed';

    /**
     * @var string
     */
    protected $table = 'hyve_schedule_overrides';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'hyve_room_id',
        'booking_date',
        'mode',
        'opening_time',
        'closing_time',
        'reason',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'booking_date' => 'date',
    ];

    public function room(): BelongsTo
    {
        return $this->belongsTo(HyveRoom::class, 'hyve_room_id');
    }

    public function isClosed(): bool
    {
        return (string) $this->mode === self::MODE_CLOSED;
    }

    public function isCustom(): bool
    {
        return (string) $this->mode === self::MODE_CUSTOM;
    }
}
