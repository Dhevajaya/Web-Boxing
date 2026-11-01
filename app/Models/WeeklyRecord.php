<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WeeklyRecord extends Model
{
    protected $fillable = ['fighter_id', 'week_start', 'w', 'l', 'd', 'ko'];

    public function fighter(): BelongsTo
    {
        return $this->belongsTo(Fighter::class);
    }
}
