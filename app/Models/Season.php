<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Season extends Model
{
    protected $fillable = ['number', 'name', 'starts_at', 'ends_at', 'status', 'config_snapshot'];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'config_snapshot' => 'array',
        ];
    }

    public static function current(): ?self
    {
        return self::where('status', 'active')->orderByDesc('number')->first();
    }
}
