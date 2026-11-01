<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ChatMessage;
use App\Models\Fight;
use App\Models\Fighter;
use App\Models\FightNightRun;
use App\Models\LoginRequest;
use App\Models\Season;
use App\Services\Game\Calendar;
use App\Services\Game\Piala;

class DashboardController extends Controller
{
    public function index(Calendar $calendar, Piala $piala)
    {
        $players = Fighter::players();

        return view('admin.dashboard', [
            'stats' => [
                'Pemain' => (clone $players)->count(),
                'Aktif 24 jam' => (clone $players)->where('last_active_at', '>=', now()->subDay())->count(),
                'Aktif 7 hari' => (clone $players)->where('last_active_at', '>=', now()->subDays(7))->count(),
                'Terverifikasi IG' => (clone $players)->whereNotNull('verified_at')->count(),
                'Disuspend' => (clone $players)->where('status', 'suspended')->count(),
                'Laga hari ini' => Fight::where('visible_at', '>=', $calendar->dayStart())->where('visible_at', '<=', now())->count(),
            ],
            'season' => Season::current(),
            'next' => $calendar->nextSlot($calendar->now()->addSecond()),
            'runs' => FightNightRun::orderByDesc('slot_at')->limit(8)->get(),
            'cup' => $piala->current(),
            'pendingLogins' => LoginRequest::where('method', 'code')->where('status', 'pending')->where('expires_at', '>', now())->count(),
            'hiddenChat' => ChatMessage::whereNotNull('hidden_at')->where('hidden_at', '>=', now()->subDay())->count(),
            'tz' => $calendar->tz(),
        ]);
    }
}
