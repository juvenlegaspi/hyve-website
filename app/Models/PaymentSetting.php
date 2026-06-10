<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentSetting extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'gcash_account_name',
        'gcash_number',
        'bank_name',
        'bank_account_name',
        'bank_account_number',
        'downpayment_percentage',
        'instructions',
        'is_active',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'downpayment_percentage' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
