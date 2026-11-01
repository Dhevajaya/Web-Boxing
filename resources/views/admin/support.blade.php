@extends('admin.layout')
@section('title', 'Dukung')
@section('content')
@php($tz = app(\App\Services\Game\Calendar::class)->tz())
@php($g = app(\App\Support\GameConfig::class))
<h1>Dukung</h1>
<div class="card">
  <p>Total donasi tercatat: <b>Rp {{ number_format($total, 0, ',', '.') }}</b></p>
  <p class="muted">Webhook Saweria: <code>{{ url('/api/webhooks/saweria') }}/{{ $g->get('supporter.webhook_token') ? '••••••' : '(isi SUPPORT_WEBHOOK_TOKEN di .env)' }}</code>. Donatur menulis <b>@username</b> di pesan donasi agar tercocokkan.</p>
</div>
<form method="post" class="card row">
  @csrf <b>Catat donasi manual</b>
  <input name="username" placeholder="@username" required> <input type="number" name="amount" placeholder="Rp" min="1" required style="width:120px"> <input name="note" placeholder="catatan"> <button class="pri">Catat</button>
</form>
<div class="card"><table>
  <tr><th>Waktu</th><th>Sumber</th><th>Donatur</th><th>Petarung</th><th>Jumlah</th><th>Pesan</th></tr>
  @forelse ($donations as $d)
    <tr><td class="muted">{{ \Carbon\Carbon::parse($d->created_at)->setTimezone($tz)->format('d/m H:i') }}</td><td>{{ $d->provider }}</td><td>{{ $d->donor_name }}</td>
      <td>{!! $d->matched ? e('@'.$d->matched) : '<span class="bad">tidak cocok'.($d->username ? ' (@'.e($d->username).')' : '').'</span>' !!}</td>
      <td>Rp {{ number_format($d->amount, 0, ',', '.') }}</td><td>{{ $d->message }}</td></tr>
  @empty <tr><td colspan="6" class="muted">Belum ada donasi.</td></tr> @endforelse
</table>{{ $donations->links('admin.pager') }}</div>
@endsection
