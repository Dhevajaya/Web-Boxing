@extends('admin.layout')
@section('title', 'Akun')
@section('content')
<h1>Akunku</h1>
<div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(320px,1fr))">
  <form method="post" class="card stack">
    @csrf <input type="hidden" name="action" value="password">
    <h2>Ganti password</h2>
    <label>Password sekarang <input type="password" name="current" required autocomplete="current-password"></label>
    <label>Password baru (min. 10) <input type="password" name="password" required autocomplete="new-password"></label>
    <label>Ulangi <input type="password" name="password_confirmation" required autocomplete="new-password"></label>
    <div><button class="pri">Simpan</button></div>
  </form>
  <div class="card stack">
    <h2>Autentikator (2FA)</h2>
    @if ($admin->totp_secret)
      <p class="ok">Aktif. Login butuh kode 6 digit dari aplikasi autentikator.</p>
      <form method="post" class="stack">@csrf<input type="hidden" name="action" value="totp_off">
        <label>Password sekarang <input type="password" name="current" required></label>
        <div><button class="danger">Matikan autentikator</button></div></form>
    @else
      <p>Tambahkan akun di Google Authenticator / Authy / 2FAS dengan kunci ini (pilih "masukkan kunci penyiapan"):</p>
      <p><code style="font-size:16px;letter-spacing:.08em;word-break:break-all">{{ $pending }}</code></p>
      <p class="muted" style="word-break:break-all">atau URI: <code>{{ $uri }}</code></p>
      <form method="post" class="row">@csrf<input type="hidden" name="action" value="totp_on">
        <input name="otp" inputmode="numeric" maxlength="8" placeholder="kode 6 digit" required><button class="pri">Aktifkan</button></form>
    @endif
  </div>
</div>
@endsection
