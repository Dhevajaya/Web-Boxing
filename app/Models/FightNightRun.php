<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FightNightRun extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['plan'];

    protected function casts(): array
    {
        return [
            'slot_at' => 'datetime',
            'prepared_at' => 'datetime',
            'published_at' => 'datetime',
            'plan' => 'array',
        ];
    }
}
