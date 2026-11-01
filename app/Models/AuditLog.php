<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['data' => 'array'];
    }

    public static function record(string $action, ?Model $subject = null, array $data = [], string $actorType = 'system', ?int $actorId = null): self
    {
        return self::create([
            'actor_type' => $actorType,
            'actor_id' => $actorId,
            'action' => $action,
            'subject_type' => $subject ? class_basename($subject) : null,
            'subject_id' => $subject?->getKey(),
            'data' => $data ?: null,
            'ip_hash' => request()?->ip() ? hash('sha256', request()->ip().config('app.key')) : null,
        ]);
    }
}
