<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IgOutbox extends Model
{
    protected $table = 'ig_outbox';

    protected $fillable = ['ig_scoped_id', 'kind', 'body', 'status', 'attempts', 'send_after', 'sent_at', 'error'];

    protected function casts(): array
    {
        return [
            'body' => 'array',
            'send_after' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }

    public static function queue(string $igsid, string $kind, string $text): self
    {
        return self::create([
            'ig_scoped_id' => $igsid,
            'kind' => $kind,
            'body' => ['text' => $text],
            'status' => 'queued',
            'send_after' => now(),
        ]);
    }
}
