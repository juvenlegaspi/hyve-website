<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Space extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'slug',
        'name',
        'tag',
        'description',
        'image',
        'note',
        'capacity',
        'features',
        'is_active',
        'sort_order',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'features' => 'array',
        'is_active' => 'boolean',
    ];

    public function bookingDetails(): HasMany
    {
        return $this->hasMany(BookingDetail::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
