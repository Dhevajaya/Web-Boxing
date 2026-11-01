@extends('admin.layout')
@section('title', 'Operasi')
@section('content')
@php($tz = app(\App\Services\Game\Calendar::class)->tz())
<h1>Operasi</h1>
<div class="card"><h2>Fight Night</h2>
  <p class="muted">Berjalan otomatis lewat cron tiap menit. Run gagal bisa diulang dari langkah terakhir yang aman. Manual dari terminal: <code>php artisan fightnight:run</code>.</p>
  <table><tr><th>Slot (WIB)</th><th>Status</th><th>Laga</th><th>Diterapkan</th><th>Terbit</th><th></th></tr>
  @forelse ($runs as $run)
    <tr><td>{{ $run->slot_at->setTimezone($tz)->format('d/m H:i') }}</td>
      <td><span @class(['pill', 'ok' => $run->status === 'done', 'bad' => $run->status === 'failed'])>{{ $run->status }}</span> <span class="bad">{{ $run->error }}</span></td>
      <td>{{ $run->pairs_total }}</td><td>{{ $run->applied }}</td><td class="muted">{{ $run->published_at?->setTimezone($tz)->format('H:i') }}</td>
      <td>@if($run->status === 'failed')<form method="post" action="{{ route('admin.runs.retry', $run) }}">@csrf<button>Ulangi</button></form>@endif</td></tr>
  @empty <tr><td colspan="6" class="muted">Belum ada run.</td></tr> @endforelse
  </table>
</div>
<div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(320px,1fr))">
  <div class="card"><h2>Season</h2>
    <p>Aktif: <b>{{ $season?->name ?? '-' }}</b> @if($season)<span class="muted">sejak {{ $season->starts_at->setTimezone($tz)->translatedFormat('d M Y') }}</span>@endif</p>
    <form method="post" action="{{ route('admin.season.close') }}" class="stack" onsubmit="return confirm('Tutup season sekarang? Tidak bisa dibatalkan.')">
      @csrf
      <p class="muted">Menutup season membekukan peringkat, memberi lencana Juara ke {{ app(\App\Support\GameConfig::class)->get('season.champion_top') }} besar, dan memulai season baru dengan bintang 0. XP dibawa {{ app(\App\Support\GameConfig::class)->get('season.xp_carry_percent') }}%.</p>
      <label>Nama season baru <input name="name" placeholder="Season {{ ($season?->number ?? 0) + 1 }}" maxlength="64"></label>
      <label>Ketik <code>TUTUP</code> untuk konfirmasi <input name="confirm" autocomplete="off" required></label>
      <div><button class="danger">Tutup season</button></div>
    </form>
    <table style="margin-top:10px">@foreach ($seasons as $s)<tr><td>{{ $s->name }}</td><td>{{ $s->status }}</td><td class="muted">{{ $s->ends_at?->setTimezone($tz)->translatedFormat('d M Y') }}</td></tr>@endforeach</table>
  </div>
  <div class="card"><h2>Piala Mingguan</h2>
    <p>Minggu {{ \Carbon\Carbon::parse($cup->week_start)->translatedFormat('d M Y') }}: <span class="pill">{{ $cup->status }}</span> · {{ $cup->entrants }} peserta</p>
    @if (in_array($cup->status, ['open', 'drawn']))
      <form method="post" action="{{ route('admin.cup.cancel', $cup) }}" onsubmit="return confirm('Batalkan piala minggu ini?')">@csrf<button class="danger">Batalkan piala ini</button></form>
    @endif
    <table style="margin-top:10px">@foreach ($cups as $c)<tr><td>{{ \Carbon\Carbon::parse($c->week_start)->translatedFormat('d M') }}</td><td>{{ $c->status }}</td><td>{{ $c->entrants }}</td><td>{{ $c->champion_id ? '@'.\App\Models\Fighter::find($c->champion_id)?->instagram_username : '' }}</td></tr>@endforeach</table>
  </div>
</div>
@endsection
