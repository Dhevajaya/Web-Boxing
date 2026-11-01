@extends('admin.layout')
@section('title', 'Login manual')
@section('content')
@php($tz = app(\App\Services\Game\Calendar::class)->tz())
<h1>Login manual</h1>
<div class="card">
  <p class="muted">Dipakai saat Instagram API belum tersambung atau DM bermasalah. Buka DM akun resmi, pastikan kode dikirim dari akun dengan <b>username yang sama</b>, lalu ketik kodenya di sini.</p>
  <form method="post" class="row">@csrf
    <input name="code" placeholder="KODE-DARI-DM" autocomplete="off" style="text-transform:uppercase;font-family:monospace" required>
    <button class="pri">Setujui</button>
  </form>
</div>
<div class="card"><h2>Menunggu ({{ $pending->count() }})</h2><table>
  <tr><th>Username</th><th>Diminta</th><th>Kedaluwarsa</th><th></th></tr>
  @forelse ($pending as $p)
    <tr><td>{{ '@'.$p->requested_username }}</td><td>{{ $p->created_at->setTimezone($tz)->format('d/m H:i') }}</td><td>{{ $p->expires_at->diffForHumans() }}</td>
      <td><form method="post" action="{{ route('admin.logins.reject', $p) }}">@csrf<button class="danger">Tolak</button></form></td></tr>
  @empty <tr><td colspan="4" class="muted">Tidak ada permintaan.</td></tr> @endforelse
</table></div>
<div class="card"><h2>Terakhir diproses</h2><table>
  @foreach ($recent as $p)<tr><td>{{ '@'.$p->requested_username }}</td><td>{{ $p->method }}</td><td>{{ $p->status }}</td><td class="muted">{{ $p->updated_at->setTimezone($tz)->format('d/m H:i') }}</td></tr>@endforeach
</table></div>
@endsection
