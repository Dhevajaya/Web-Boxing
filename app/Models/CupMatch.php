<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CupMatch extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['scheduled_at' => 'datetime', 'walkover' => 'boolean'];
    }
}
