<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HyveRecurringClosure extends Model
{
    use HasFactory;

    public const SUNDAY = 0;

    protected $fillable = [
        'weekday',
        'is_active',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'weekday' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
