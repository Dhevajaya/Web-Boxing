<?php

namespace Tests\Feature\Concerns;

use App\Models\Fighter;
use App\Models\PlayerSession;
use App\Services\Auth\PlayerAuth;

trait MakesFighters
{
    protected function player(string $u, array $attrs = []): Fighter
    {
        return Fighter::create($attrs + [
            'instagram_username' => $u, 'status' => 'active', 'activated_at' => now(), 'last_active_at' => now(),
        ]);
    }

    protected function loginAs(Fighter $f): Fighter
    {
        $row = PlayerSession::create(['fighter_id' => $f->id, 'session_id_hash' => hash('sha256', $f->id.'-'.microtime())]);
        $this->actingAs($f, 'fighter')->withSession([PlayerAuth::KEY => $row->id]);

        return $f;
    }
}
