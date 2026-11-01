<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatMessage extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['hidden_at' => 'datetime', 'created_at' => 'datetime'];
    }

    public function reports(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ChatReport::class, 'message_id');
    }

    public function fighter(): BelongsTo
    {
        return $this->belongsTo(Fighter::class);
    }
}
