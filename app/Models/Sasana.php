<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sasana extends Model
{
    protected $fillable = ['slug', 'name', 'icon', 'color', 'motto'];

    public function fighters(): HasMany
    {
        return $this->hasMany(Fighter::class);
    }
}
