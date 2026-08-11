<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SupportConversation extends Model
{
    use HasFactory;

    public const STATUS_OPEN = 'open';

    public const STATUS_CLOSED = 'closed';

    public const MODE_ASSISTANT = 'assistant';

    public const MODE_FRONT_DESK = 'front_desk';

    protected $fillable = [
        'public_token',
        'user_id',
        'assigned_user_id',
        'customer_name',
        'email',
        'phone',
        'status',
        'mode',
        'last_message_at',
        'customer_last_read_at',
        'customer_last_read_message_id',
        'admin_last_read_at',
        'admin_last_read_message_id',
    ];

    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
            'customer_last_read_at' => 'datetime',
            'admin_last_read_at' => 'datetime',
        ];
    }

    public function messages(): HasMany
    {
        return $this->hasMany(SupportMessage::class);
    }

    public function latestMessage(): HasOne
    {
        return $this->hasOne(SupportMessage::class)->latestOfMany();
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function scopeWithUnreadForAdmin(Builder $query): Builder
    {
        return $query->where('mode', self::MODE_FRONT_DESK)->where(function (Builder $unread): void {
            $unread->whereHas('messages', function (Builder $messages): void {
                $messages->where('sender_type', SupportMessage::SENDER_CUSTOMER)
                    ->whereColumn('support_messages.id', '>', 'support_conversations.admin_last_read_message_id');
            })->orWhere(function (Builder $conversation): void {
                $conversation->whereNull('admin_last_read_message_id')
                    ->whereHas('messages', fn (Builder $messages) => $messages->where('sender_type', SupportMessage::SENDER_CUSTOMER));
            });
        });
    }
}
