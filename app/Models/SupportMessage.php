<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupportMessage extends Model
{
    use HasFactory;

    public const SENDER_CUSTOMER = 'customer';

    public const SENDER_ADMIN = 'admin';

    public const SENDER_ASSISTANT = 'assistant';

    protected $fillable = [
        'support_conversation_id',
        'sender_user_id',
        'reply_to_message_id',
        'sender_type',
        'body',
        'action_type',
        'action_label',
        'action_url',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(SupportConversation::class, 'support_conversation_id');
    }

    public function senderUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_user_id');
    }

    public function replyTo(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reply_to_message_id');
    }

    public function reactions(): HasMany
    {
        return $this->hasMany(SupportMessageReaction::class);
    }
}
