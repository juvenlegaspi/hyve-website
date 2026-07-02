<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

class HyveCalendarEvent extends Model
{
    use HasFactory;

    public const TYPE_HOLIDAY = 'holiday';
    public const TYPE_CUSTOM = 'custom';
    public const TYPE_BLOCKED = 'blocked';

    public const SCOPE_ALL_ROOMS = 'all_rooms';
    public const SCOPE_SELECTED_ROOMS = 'selected_rooms';

    public const SOURCE_SYSTEM = 'system';
    public const SOURCE_ADMIN = 'admin';

    /**
     * @var string
     */
    protected $table = 'hyve_calendar_events';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'title',
        'type',
        'scope',
        'source',
        'start_date',
        'end_date',
        'start_time',
        'end_time',
        'all_day',
        'affects_booking',
        'status',
        'notes',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'all_day' => 'boolean',
        'affects_booking' => 'boolean',
        'status' => 'boolean',
    ];

    public function rooms(): BelongsToMany
    {
        return $this->belongsToMany(
            HyveRoom::class,
            'hyve_calendar_event_room',
            'hyve_calendar_event_id',
            'hyve_room_id',
        );
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', true);
    }

    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->whereDate('end_date', '>=', Carbon::today()->toDateString());
    }

    public function scopeForDate(Builder $query, string $date): Builder
    {
        return $query
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date);
    }

    public function appliesToRoom(HyveRoom $room): bool
    {
        if ((string) $this->scope === self::SCOPE_ALL_ROOMS) {
            return true;
        }

        return $this->rooms->contains(fn (HyveRoom $linkedRoom): bool => $linkedRoom->id === $room->id);
    }

    public function isHoliday(): bool
    {
        return (string) $this->type === self::TYPE_HOLIDAY;
    }

    public function isBlocked(): bool
    {
        return (string) $this->type === self::TYPE_BLOCKED;
    }

    public function isCustom(): bool
    {
        return (string) $this->type === self::TYPE_CUSTOM;
    }

    public function isAllDay(): bool
    {
        return (bool) $this->all_day;
    }
}
