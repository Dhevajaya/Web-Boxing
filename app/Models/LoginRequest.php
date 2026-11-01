<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class LoginRequest extends Model
{
    use HasUuids;

    protected $guarded = ['id'];

    protected $hidden = ['ref_hash', 'token_hash', 'code_hash', 'ip_hash'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'verified_at' => 'datetime',
            'used_at' => 'datetime',
        ];
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /** Token dan kode tidak pernah disimpan mentah. */
    public static function hash(string $secret): string
    {
        return hash_hmac('sha256', $secret, (string) config('app.key'));
    }
}
