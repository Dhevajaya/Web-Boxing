<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IgEvent extends Model
{
    protected $fillable = ['dedupe_key', 'type', 'ig_scoped_id', 'payload', 'processed_at', 'error'];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'processed_at' => 'datetime',
        ];
    }
}
