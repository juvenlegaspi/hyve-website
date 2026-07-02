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
        'gcash_qr_path',
        'bank_name',
        'bank_account_name',
        'bank_account_number',
        'bank_qr_path',
        'downpayment_percentage',
        'instructions',
        'mikrotik_enabled',
        'mikrotik_host',
        'mikrotik_port',
        'mikrotik_username',
        'mikrotik_password',
        'mikrotik_hotspot_server',
        'mikrotik_dns_name',
        'is_active',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'downpayment_percentage' => 'decimal:2',
        'mikrotik_enabled' => 'boolean',
        'mikrotik_password' => 'encrypted',
        'is_active' => 'boolean',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function hasMikrotikSetup(): bool
    {
        return $this->mikrotik_enabled
            && filled($this->mikrotik_host)
            && filled($this->mikrotik_username)
            && filled($this->mikrotik_password);
    }
}
