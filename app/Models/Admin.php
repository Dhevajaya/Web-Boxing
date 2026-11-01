<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

class Admin extends Authenticatable
{
    protected $fillable = ['name', 'email', 'password', 'role'];

    protected $hidden = ['password', 'remember_token', 'totp_secret'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'totp_secret' => 'encrypted',
            'last_login_at' => 'datetime',
        ];
    }

    /** Peran bertingkat: owner > moderator > editor. */
    public function hasRole(string $role): bool
    {
        $rank = ['editor' => 1, 'moderator' => 2, 'owner' => 3];

        return ($rank[$this->role] ?? 0) >= ($rank[$role] ?? 99);
    }
}
