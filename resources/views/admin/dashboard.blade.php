@extends('admin.layout')
@section('title', 'Dasbor')
@section('content')
<h1>Dasbor</h1>
<div class="grid">
  @foreach ($stats as $label => $n)<div class="stat"><b>{{ number_format($n, 0, ',', '.') }}</b><small>{{ $label }}</small></div>@endforeach
</div>
<div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(260px,1fr))">
  <div class="card">
    <h2>Malam Gelanggang</h2>
    <p>Berikutnya: <b>{{ $next->setTimezone($tz)->translatedFormat('l, d M H:i') }} WIB</b></p>
    <table><tr><th>Slot (WIB)</th><th>Status</th><th>Laga</th></tr>
      @forelse ($runs as $run)
        <tr><td>{{ $run->slot_at->setTimezone($tz)->format('d/m H:i') }}</td>
          <td><span @class(['pill', 'ok' => $run->status === 'done', 'bad' => $run->status === 'failed'])>{{ $run->status }}</span> @if($run->error)<span class="bad">{{ $run->error }}</span>@endif</td>
          <td>{{ $run->pairs_total }}</td></tr>
      @empty <tr><td colspan="3" class="muted">Belum ada. Pastikan cron <code>schedule:run</code> berjalan tiap menit.</td></tr> @endforelse
    </table>
  </div>
  <div class="card">
    <h2>Season & Piala</h2>
    <p>{{ $season?->name ?? 'Tidak ada season aktif' }} @if($season)<span class="muted">sejak {{ $season->starts_at->setTimezone($tz)->translatedFormat('d M Y') }}</span>@endif</p>
    <p>Piala minggu {{ \Carbon\Carbon::parse($cup->week_start)->translatedFormat('d M') }}: <span class="pill">{{ $cup->status }}</span> {{ $cup->entrants ?: '' }}</p>
    <h2 style="margin-top:14px">Perlu perhatian</h2>
    <p>Login manual menunggu: <b>{{ $pendingLogins }}</b> @if(auth('admin')->user()->hasRole('moderator'))· <a href="{{ route('admin.logins') }}">buka</a>@endif</p>
    <p>Pesan chat disembunyikan (24 jam): <b>{{ $hiddenChat }}</b> @if(auth('admin')->user()->hasRole('moderator'))· <a href="{{ route('admin.chat', ['filter' => 'hidden']) }}">tinjau</a>@endif</p>
  </div>
</div>
@endsection
