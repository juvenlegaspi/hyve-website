<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable([
    'username',
    'first_name',
    'last_name',
    'email',
    'number',
    'password',
    'status',
    'role',
])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const ROLE_MEMBER = 'member';
    public const ROLE_ADMIN = 'admin';
    public const ROLE_SUPER_ADMIN = 'super_admin';
    public const ROLE_FRONT_DESK = 'front_desk';
    public const ROLE_AUDIT = 'audit';

    protected $table = 'booking_users';

    protected $hidden = [
        'password',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'status' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function bookingHeaders(): HasMany
    {
        return $this->hasMany(BookingHeader::class);
    }

    public function bookingActivities(): HasMany
    {
        return $this->hasMany(BookingActivity::class, 'actor_user_id');
    }

    public function bookingPayments(): HasMany
    {
        return $this->hasMany(BookingPayment::class);
    }

    public function getNameAttribute(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }

    public function getPhoneAttribute(): string
    {
        return (string) ($this->number ?? '');
    }

    public function isAdmin(): bool
    {
        return $this->canAccessAdminPanel();
    }

    public function isSuperAdmin(): bool
    {
        return (string) $this->role === self::ROLE_SUPER_ADMIN;
    }

    public function canAccessAdminPanel(): bool
    {
        return in_array((string) $this->role, self::adminPanelRoles(), true);
    }

    public function hasPermission(string $permission): bool
    {
        if (! $this->canAccessAdminPanel()) {
            return false;
        }

        $rolePermissions = config('admin_permissions.role_permissions.'.(string) $this->role, []);

        return in_array('*', $rolePermissions, true) || in_array($permission, $rolePermissions, true);
    }

    /**
     * @return array<int, string>
     */
    public static function adminPanelRoles(): array
    {
        return [
            self::ROLE_ADMIN,
            self::ROLE_SUPER_ADMIN,
            self::ROLE_FRONT_DESK,
            self::ROLE_AUDIT,
        ];
    }
}
