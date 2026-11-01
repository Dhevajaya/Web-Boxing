@extends('admin.layout')
@section('title', '@'.$f->instagram_username)
@section('content')
@php($tz = app(\App\Services\Game\Calendar::class)->tz())
<div class="row"><a href="{{ route('admin.fighters') }}">← Petarung</a><h1>{{ '@'.$f->instagram_username }}</h1><a href="/{{ $f->instagram_username }}" target="_blank" rel="noopener">lihat kartu ↗</a></div>
<div class="grid">
  <div class="stat"><b>{{ $f->status }}</b><small>Status</small></div>
  <div class="stat"><b>{{ $f->level }}</b><small>Level ({{ $f->xp }} XP)</small></div>
  <div class="stat"><b>{{ $sessions }}</b><small>Sesi aktif</small></div>
  <div class="stat"><b>{{ $f->supporter_tier ?? '-' }}</b><small>Pendukung</small></div>
</div>
<div class="card">
  <h2>Data</h2>
  <table>
    <tr><td>Julukan / kota</td><td>{{ $f->nick ?? '(bawaan)' }} / {{ $f->kota ?? '(bawaan)' }}</td></tr>
    <tr><td>Terverifikasi IG</td><td>{{ $f->verified_at?->setTimezone($tz)->translatedFormat('d M Y H:i') ?? 'belum (login manual/dev)' }}</td></tr>
    <tr><td>Aktif sejak</td><td>{{ $f->activated_at?->setTimezone($tz)->translatedFormat('d M Y H:i') ?? '-' }}</td></tr>
    <tr><td>Terakhir aktif</td><td>{{ $f->last_active_at?->setTimezone($tz)->translatedFormat('d M Y H:i') ?? '-' }}</td></tr>
    <tr><td>Dibisukan sampai</td><td>{{ $f->muted_until?->isFuture() ? $f->muted_until->setTimezone($tz)->translatedFormat('d M H:i') : '-' }}</td></tr>
    <tr><td>Lencana</td><td>{{ $badges->pluck('code')->implode(', ') ?: '-' }}</td></tr>
  </table>
</div>
<div class="card">
  <h2>Tindakan</h2>
  <div class="row" style="gap:12px">
    @php($act = fn ($a, $label, $cls = '') => '<form method="post" class="row">'.csrf_field().'<input type="hidden" name="action" value="'.$a.'"><button class="'.$cls.'">'.$label.'</button></form>')
    {!! $f->status === 'suspended' ? $act('unsuspend', 'Cabut suspend') : $act('suspend', 'Suspend', 'danger') !!}
    {!! $f->muted_until?->isFuture() ? $act('unmute', 'Cabut bisu') : '' !!}
    {!! $act('reset_identity', 'Reset julukan/kota') !!}
    {!! $act('reset_look', 'Reset tampilan') !!}
    {!! $act('revoke_sessions', 'Cabut semua sesi') !!}
  </div>
  <div class="row" style="margin-top:12px;gap:16px">
    <form method="post" class="row">@csrf<input type="hidden" name="action" value="mute"><input type="number" name="hours" value="24" min="1" max="720" style="width:80px"> jam <button>Bisukan</button></form>
    <form method="post" class="row">@csrf<input type="hidden" name="action" value="xp"><input type="number" name="amount" placeholder="±XP" style="width:90px"><input name="note" placeholder="alasan"><button>Sesuaikan XP</button></form>
    <form method="post" class="row">@csrf<input type="hidden" name="action" value="supporter">
      <select name="tier"><option value="">bukan pendukung</option>@foreach (['tribun','ringside','vip'] as $t)<option @selected($f->supporter_tier === $t)>{{ $t }}</option>@endforeach</select><button>Set pendukung</button></form>
  </div>
</div>
<div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(320px,1fr))">
  <div class="card"><h2>Laga terakhir</h2><table>
    @forelse ($fights as $x)
      @php($side = $x->a_id === $f->id ? 'a' : 'b')
      <tr><td>{{ $x->visible_at->setTimezone($tz)->format('d/m H:i') }}</td><td>{{ $x->kind }}</td>
        <td>vs {{ '@'.($side === 'a' ? $x->fighterB?->instagram_username : $x->fighterA?->instagram_username) }}</td>
        <td>{{ $x->winner === 'draw' ? 'seri' : ($x->winner === $side ? 'menang' : 'kalah') }} ({{ $x->how }})</td><td><a href="/laga/{{ $x->id }}" target="_blank">tonton</a></td></tr>
    @empty <tr><td class="muted">Belum ada.</td></tr> @endforelse
  </table></div>
  <div class="card"><h2>XP terakhir</h2><table>
    @forelse ($xpLog as $x)<tr><td>{{ $x->source }}</td><td>{{ $x->amount > 0 ? '+' : '' }}{{ $x->amount }}</td><td class="muted">{{ \Carbon\Carbon::parse($x->created_at)->setTimezone($tz)->format('d/m H:i') }}</td></tr>
    @empty <tr><td class="muted">Belum ada.</td></tr> @endforelse
  </table></div>
</div>
<div class="card"><h2>Log</h2><table>
  @forelse ($logs as $l)<tr><td class="muted">{{ $l->created_at->setTimezone($tz)->format('d/m H:i') }}</td><td>{{ $l->action }}</td><td>{{ $l->actor_type }}{{ $l->actor_id ? '#'.$l->actor_id : '' }}</td><td><code>{{ json_encode($l->data, JSON_UNESCAPED_UNICODE) }}</code></td></tr>
  @empty <tr><td class="muted">Belum ada.</td></tr> @endforelse
</table></div>
@endsection
