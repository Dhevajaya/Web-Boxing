<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cup extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['drawn_at' => 'datetime', 'finished_at' => 'datetime'];
    }

    public function entries(): HasMany
    {
        return $this->hasMany(CupEntry::class);
    }

    public function matches(): HasMany
    {
        return $this->hasMany(CupMatch::class);
    }
}
