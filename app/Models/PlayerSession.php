<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlayerSession extends Model
{
    protected $fillable = ['fighter_id', 'session_id_hash', 'user_agent', 'last_seen_at', 'revoked_at'];

    protected function casts(): array
    {
        return [
            'last_seen_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public static function hashId(string $sessionId): string
    {
        return hash_hmac('sha256', $sessionId, (string) config('app.key'));
    }
}
