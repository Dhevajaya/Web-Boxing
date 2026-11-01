<?php

namespace App\Http\Controllers\Api;

use App\Game\NameRules;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\ChatMessage;
use App\Models\Fighter;
use App\Models\Sasana;
use App\Support\GameConfig;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Markas: chat polling (cocok untuk shared hosting, tanpa websocket).
 *   alun           : semua orang bisa membaca, pemain masuk bisa menulis
 *   sasana:{slug}  : hanya anggota sasana itu (season ini)
 */
class ChatController extends Controller
{
    public function __construct(private readonly GameConfig $game) {}

    public function index(Request $request, string $room)
    {
        if ($err = $this->canRead($room)) {
            return $err;
        }
        $cap = (int) $this->game->get('chat.room_cap');
        $after = $request->integer('after');
        $q = ChatMessage::where('room', $room)->whereNull('hidden_at')->with('fighter');
        $rows = $after
            ? $q->where('id', '>', $after)->orderBy('id')->limit($cap)->get()
            : $q->orderByDesc('id')->limit($cap)->get()->reverse()->values();

        // Pesan yang disembunyikan sejak polling terakhir, agar browser bisa menghapusnya.
        $hidden = $after ? ChatMessage::where('room', $room)->whereNotNull('hidden_at')
            ->where('hidden_at', '>=', now()->subMinutes(10))->pluck('id') : [];

        /** @var Fighter|null $me */
        $me = Auth::guard('fighter')->user();

        return $this->ok([
            'room' => $room,
            'messages' => $rows->map(fn (ChatMessage $m) => $this->message($m))->values(),
            'hidden' => $hidden,
            'me' => $me ? [
                'muted_until' => $me->muted_until?->isFuture() ? $me->muted_until->toIso8601String() : null,
            ] : null,
        ])->header('Cache-Control', 'no-store');
    }

    public function store(Request $request, string $room, NameRules $names)
    {
        if ($err = $this->canRead($room)) {
            return $err;
        }
        /** @var Fighter $me */
        $me = Fighter::find(Auth::guard('fighter')->id());
        if ($me->muted_until?->isFuture()) {
            return $this->fail('muted', 'Kamu dibisukan sampai '.$me->muted_until->setTimezone($this->game->get('brand.timezone'))->format('d/m H:i').' WIB.', 403);
        }

        $body = trim((string) preg_replace('/\s+/u', ' ', (string) $request->input('body')));
        $max = (int) $this->game->get('chat.max_len');
        if ($body === '' || mb_strlen($body) > $max) {
            return $this->fail('invalid', "Pesan 1 sampai {$max} karakter.");
        }
        if ($names->isBanned($body)) {
            return $this->fail('banned', 'Pesan mengandung kata yang tidak diizinkan.');
        }
        $cool = (int) $this->game->get('chat.cooldown_sec');
        $last = ChatMessage::where('fighter_id', $me->id)->latest('id')->value('created_at');
        if ($last && now()->diffInSeconds($last, true) < $cool) {
            return $this->fail('cooldown', "Tunggu {$cool} detik di antara pesan.", 429);
        }

        $m = ChatMessage::create(['room' => $room, 'fighter_id' => $me->id, 'body' => $body, 'created_at' => now()]);
        $m->setRelation('fighter', $me);

        return $this->ok($this->message($m), 201);
    }

    public function report(int $id)
    {
        $m = ChatMessage::find($id);
        if (! $m || $m->hidden_at) {
            return $this->fail('not_found', 'Pesan tidak ditemukan.', 404);
        }
        $me = Auth::guard('fighter')->user();
        if ($m->fighter_id === $me->id) {
            return $this->fail('self', 'Tidak bisa melaporkan pesan sendiri.');
        }
        if ($err = $this->canRead($m->room)) {
            return $err;
        }

        DB::table('chat_reports')->insertOrIgnore(['message_id' => $m->id, 'fighter_id' => $me->id, 'created_at' => now()]);
        $count = DB::table('chat_reports')->where('message_id', $m->id)->count();
        if ($count >= (int) $this->game->get('chat.report_threshold')) {
            $m->forceFill(['hidden_at' => now(), 'hidden_reason' => 'reports'])->save();
            $until = now()->addHours((int) $this->game->get('chat.mute_hours'));
            Fighter::whereKey($m->fighter_id)->update(['muted_until' => $until]);
            AuditLog::record('chat_auto_hidden', $m, ['reports' => $count, 'muted_until' => $until->toIso8601String()]);
        }

        return $this->ok(['reported' => true, 'hidden' => (bool) $m->hidden_at]);
    }

    private function canRead(string $room)
    {
        if ($room === 'alun') {
            return null;
        }
        if (! preg_match('/^sasana:([a-z0-9_-]{1,32})$/', $room, $mm) || ! Sasana::where('slug', $mm[1])->exists()) {
            return $this->fail('not_found', 'Ruang tidak ditemukan.', 404);
        }
        /** @var Fighter|null $me */
        $me = Auth::guard('fighter')->user();
        $mine = $me?->sasana_id && (int) $me->sasana_season === $this->game->currentSeasonNumber() ? $me->sasana?->slug : null;
        if ($mine !== $mm[1]) {
            return $this->fail('forbidden', 'Hanya anggota sasana ini yang bisa masuk.', 403);
        }

        return null;
    }

    private function message(ChatMessage $m): array
    {
        $f = $m->fighter;

        return [
            'id' => $m->id,
            'body' => $m->body,
            'at' => $m->created_at->toIso8601String(),
            'from' => $f ? [
                'username' => $f->instagram_username,
                'look' => $f->resolvedLook(),
                'level' => $f->levelNumber(),
                'supporter' => $f->supporter_anonymous ? null : $f->supporter_tier,
            ] : null,
        ];
    }
}
