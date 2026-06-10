<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HyveRoom extends Model
{
    use HasFactory;

    /**
     * @var string
     */
    protected $table = 'hyve_rooms';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'room_name',
        'description',
        'room_status',
        'status',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'room_status' => 'integer',
        'status' => 'integer',
    ];

    public function bookingDetails(): HasMany
    {
        return $this->hasMany(BookingDetail::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 0);
    }

    public function isConferenceRoom(): bool
    {
        return $this->room_name === 'Conference Room';
    }

    public function isPrivateRoom(): bool
    {
        return str_starts_with($this->room_name, 'Room ');
    }

    public function isSharedTable(): bool
    {
        return str_starts_with($this->room_name, 'Table ');
    }

    public function mappedSpaceSlug(): string
    {
        if ($this->isConferenceRoom()) {
            return 'zeal-room-8-seats';
        }

        if ($this->room_name === 'Room 7') {
            return 'tenacity-office-4-seats';
        }

        if ($this->isPrivateRoom()) {
            return 'fortitude-office-2-seats';
        }

        return 'common-area';
    }

    public function mappedSpaceLabel(): string
    {
        return match ($this->mappedSpaceSlug()) {
            'zeal-room-8-seats' => 'Zeal Room (8 Seats)',
            'tenacity-office-4-seats' => 'Tenacity Office (4 Seats)',
            'fortitude-office-2-seats' => 'Fortitude Office (2 Seats)',
            default => 'Common Area',
        };
    }

    public function layoutGroup(): string
    {
        if ($this->isConferenceRoom()) {
            return 'featured';
        }

        if ($this->room_name === 'Room 7') {
            return 'featured';
        }

        if ($this->isPrivateRoom()) {
            return 'private';
        }

        return 'tables';
    }
}
