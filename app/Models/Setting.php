<?php

namespace App\Models;

use App\Support\GameConfig;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['key', 'value'];

    protected function casts(): array
    {
        return ['value' => 'json'];
    }

    public static function put(string $key, mixed $value): void
    {
        self::updateOrCreate(['key' => $key], ['value' => $value]);
        app(GameConfig::class)->flush();
    }

    protected static function booted(): void
    {
        static::deleted(fn () => app(GameConfig::class)->flush());
    }
}
