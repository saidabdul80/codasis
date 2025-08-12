<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Conversation extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'title',
        'model',
        'last_message_at',
        'metadata',
    ];

    protected $casts = [
        'last_message_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function getLastMessageAttribute(): ?Message
    {
        return $this->messages()->latest()->first();
    }

    public function getMessageCountAttribute(): int
    {
        return $this->messages()->count();
    }

    public function getUserMessageCountAttribute(): int
    {
        return $this->messages()->where('role', 'user')->count();
    }

    public function getAssistantMessageCountAttribute(): int
    {
        return $this->messages()->where('role', 'assistant')->count();
    }
}
