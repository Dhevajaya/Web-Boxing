<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Announcement extends Model
{
    protected $fillable = ['title', 'body', 'pinned', 'published_at', 'admin_id'];

    protected function casts(): array
    {
        return ['pinned' => 'boolean', 'published_at' => 'datetime'];
    }

    public function scopePublished(Builder $q): Builder
    {
        return $q->whereNotNull('published_at')->where('published_at', '<=', now());
    }
}
