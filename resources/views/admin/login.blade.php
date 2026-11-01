@extends('admin.layout')
@section('title', 'Masuk admin')
@section('content')
<div class="card" style="max-width:380px;margin:40px auto;width:100%">
  <h1 style="margin-bottom:12px">Masuk admin</h1>
  <form method="post" class="stack">
    @csrf
    <label>Email <input type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username"></label>
    <label>Password <input type="password" name="password" required autocomplete="current-password"></label>
    <label>Kode autentikator <span class="muted">(bila diaktifkan)</span> <input name="otp" inputmode="numeric" autocomplete="one-time-code" maxlength="8"></label>
    <button class="pri">Masuk</button>
  </form>
</div>
@endsection
