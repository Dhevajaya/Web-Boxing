@extends('admin.layout')
@section('title', 'Admin')
@section('content')
<h1>Akun admin</h1>
<div class="card"><table>
  <tr><th>Nama</th><th>Email</th><th>Peran</th><th>Autentikator</th><th>Login terakhir</th><th></th></tr>
  @foreach ($admins as $a)
    <tr><td>{{ $a->name }}</td><td>{{ $a->email }}</td><td>{{ $a->role }}</td><td>{!! $a->totp_secret ? '<span class="ok">aktif</span>' : '<span class="muted">tidak</span>' !!}</td>
      <td class="muted">{{ $a->last_login_at?->diffForHumans() ?? '-' }}</td>
      <td>@if(! $a->is(auth('admin')->user()))<form method="post" action="{{ route('admin.admins.delete', $a) }}" onsubmit="return confirm('Hapus admin ini?')">@csrf<button class="danger">Hapus</button></form>@endif</td></tr>
  @endforeach
</table></div>
<form method="post" class="card stack" style="max-width:480px">
  @csrf
  <h2>Tambah admin</h2>
  <label>Nama <input name="name" value="{{ old('name') }}" required maxlength="64"></label>
  <label>Email <input type="email" name="email" value="{{ old('email') }}" required></label>
  <label>Password awal (min. 10 karakter) <input type="password" name="password" required autocomplete="new-password"></label>
  <label>Peran <select name="role"><option value="editor">editor: pengumuman</option><option value="moderator">moderator: + petarung, chat, login manual</option><option value="owner">owner: semuanya</option></select></label>
  <div><button class="pri">Tambah</button></div>
</form>
@endsection
