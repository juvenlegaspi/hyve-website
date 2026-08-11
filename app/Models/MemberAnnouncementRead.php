<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemberAnnouncementRead extends Model
{
    use HasFactory;

    protected $fillable = ['member_announcement_id', 'user_id', 'read_at'];

    protected $casts = ['read_at' => 'datetime'];

    public function announcement(): BelongsTo
    {
        return $this->belongsTo(MemberAnnouncement::class, 'member_announcement_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
