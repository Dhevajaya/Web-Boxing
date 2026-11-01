<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FighterRecord extends Model
{
    protected $fillable = ['fighter_id', 'season_id', 'w', 'l', 'd', 'ko', 'stars', 'last_fight_id'];

    public function fighter(): BelongsTo
    {
        return $this->belongsTo(Fighter::class);
    }

    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }
}
