@php($admin = auth('admin')->user())
@php($brand = app(\App\Support\GameConfig::class)->get('brand.name'))
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <title>@yield('title', 'Admin') · {{ $brand }}</title>
  <style>
    :root { --bg:#0f1115; --s:#171a21; --s2:#1f232c; --line:#2a2f3a; --ink:#f2f3f5; --muted:#9aa1ad; --acc:#ffb627; --hot:#ff5a36; --ok:#3ecf8e; --bad:#ff6b6b; color-scheme: dark; }
    * { box-sizing: border-box; }
    body { margin:0; background:var(--bg); color:var(--ink); font:14px/1.5 system-ui,-apple-system,"Segoe UI",sans-serif; }
    a { color: var(--acc); }
    .top { display:flex; flex-wrap:wrap; gap:6px 14px; align-items:center; padding:10px 16px; background:var(--s); border-bottom:1px solid var(--line); position:sticky; top:0; z-index:5; }
    .top b { margin-right:8px; }
    .top a { color:var(--muted); text-decoration:none; font-weight:600; }
    .top a.on { color:var(--ink); }
    .top form { margin-left:auto; }
    main { max-width:1100px; margin:0 auto; padding:16px; display:flex; flex-direction:column; gap:16px; }
    h1 { margin:0; font-size:22px; } h2 { margin:0 0 8px; font-size:16px; }
    .card { background:var(--s); border:1px solid var(--line); border-radius:12px; padding:14px; overflow-x:auto; }
    .grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(150px,1fr)); gap:10px; }
    .stat { background:var(--s2); border-radius:10px; padding:10px 12px; } .stat b { display:block; font-size:24px; } .stat small { color:var(--muted); }
    table { width:100%; border-collapse:collapse; } th, td { text-align:left; padding:7px 8px; border-bottom:1px solid var(--line); vertical-align:top; } th { color:var(--muted); font-weight:600; font-size:12px; text-transform:uppercase; }
    input, select, textarea, button { font:inherit; color:inherit; }
    input, select, textarea { background:var(--s2); border:1px solid var(--line); border-radius:8px; padding:7px 9px; max-width:100%; }
    textarea { width:100%; min-height:160px; }
    button, .btn { background:var(--s2); border:1px solid var(--line); border-radius:8px; padding:7px 12px; cursor:pointer; font-weight:700; text-decoration:none; color:var(--ink); display:inline-block; }
    button.pri, .btn.pri { background:var(--acc); color:#1a1200; border-color:var(--acc); }
    button.danger { border-color:var(--bad); color:var(--bad); }
    .row { display:flex; flex-wrap:wrap; gap:8px; align-items:center; }
    .muted { color:var(--muted); } .ok { color:var(--ok); } .bad { color:var(--bad); }
    .flash { padding:10px 12px; border-radius:10px; background:color-mix(in srgb,var(--ok) 15%,var(--s)); border:1px solid color-mix(in srgb,var(--ok) 40%,transparent); }
    .flash.err { background:color-mix(in srgb,var(--bad) 12%,var(--s)); border-color:color-mix(in srgb,var(--bad) 40%,transparent); }
    .pill { display:inline-block; padding:1px 8px; border-radius:99px; background:var(--s2); font-size:12px; font-weight:700; }
    .pill.ok { background:color-mix(in srgb,var(--ok) 18%,transparent); } .pill.bad { background:color-mix(in srgb,var(--bad) 18%,transparent); }
    label { display:flex; flex-direction:column; gap:4px; } label.inline { flex-direction:row; align-items:center; gap:6px; }
    form.stack { display:flex; flex-direction:column; gap:10px; }
    nav.pager { display:flex; gap:6px; flex-wrap:wrap; } nav.pager a, nav.pager span { padding:4px 9px; border-radius:6px; background:var(--s2); text-decoration:none; }
    code { background:var(--s2); padding:1px 5px; border-radius:5px; }
  </style>
</head>
<body>
  @if ($admin)
  <header class="top">
    <b>⚙ {{ $brand }}</b>
    @php($r = request()->route()?->getName())
    <a href="{{ route('admin.dashboard') }}" @class(['on' => $r === 'admin.dashboard'])>Dasbor</a>
    @if ($admin->hasRole('moderator'))
      <a href="{{ route('admin.fighters') }}" @class(['on' => str_starts_with($r, 'admin.fighters')])>Petarung</a>
      <a href="{{ route('admin.logins') }}" @class(['on' => $r === 'admin.logins'])>Login manual</a>
      <a href="{{ route('admin.chat') }}" @class(['on' => $r === 'admin.chat'])>Chat</a>
    @endif
    <a href="{{ route('admin.announcements') }}" @class(['on' => str_starts_with($r, 'admin.announcements')])>Pengumuman</a>
    @if ($admin->hasRole('owner'))
      <a href="{{ route('admin.operations') }}" @class(['on' => $r === 'admin.operations'])>Operasi</a>
      <a href="{{ route('admin.settings') }}" @class(['on' => $r === 'admin.settings'])>Pengaturan</a>
      <a href="{{ route('admin.support') }}" @class(['on' => $r === 'admin.support'])>Dukung</a>
      <a href="{{ route('admin.admins') }}" @class(['on' => $r === 'admin.admins'])>Admin</a>
      <a href="{{ route('admin.audit') }}" @class(['on' => $r === 'admin.audit'])>Audit</a>
    @endif
    <a href="{{ route('admin.account') }}" @class(['on' => $r === 'admin.account'])>Akun</a>
    <form method="post" action="{{ route('admin.logout') }}">@csrf<button>Keluar</button></form>
  </header>
  @endif
  <main>
    @if (session('ok'))<div class="flash">{{ session('ok') }}</div>@endif
    @if ($errors->any())<div class="flash err">@foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>@endif
    @yield('content')
  </main>
</body>
</html>
