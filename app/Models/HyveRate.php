<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HyveRate extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'space_slug',
        'title',
        'day_use',
        'night_use',
        'memberships',
        'minimum_hours',
        'day_minimum_rate',
        'day_succeeding_hour_rate',
        'night_minimum_rate',
        'night_succeeding_hour_rate',
        'is_active',
        'sort_order',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'day_use' => 'array',
        'night_use' => 'array',
        'memberships' => 'array',
        'minimum_hours' => 'integer',
        'day_minimum_rate' => 'decimal:2',
        'day_succeeding_hour_rate' => 'decimal:2',
        'night_minimum_rate' => 'decimal:2',
        'night_succeeding_hour_rate' => 'decimal:2',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * @return array<string, mixed>
     */
    public function toDisplayArray(): array
    {
        return [
            'title' => $this->title,
            'day_use' => $this->day_use ?? [],
            'night_use' => $this->night_use ?? [],
            'memberships' => $this->memberships ?? [],
        ];
    }
}
