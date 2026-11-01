<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex">
  <title>@yield('code') · {{ config('game.brand.name') }}</title>
  <style>
    body { margin:0; min-height:100vh; display:grid; place-items:center; background:#0f1115; color:#f2f3f5; font:16px/1.5 system-ui,-apple-system,"Segoe UI",sans-serif; text-align:center; padding:24px; box-sizing:border-box; }
    b { display:block; font-size:72px; line-height:1; color:#ffb627; font-family:Impact,"Arial Narrow",sans-serif; }
    p { color:#9aa1ad; max-width:34ch; margin:12px auto 20px; }
    a { color:#1a1200; background:#ffb627; padding:10px 16px; border-radius:12px; font-weight:800; text-decoration:none; }
  </style>
</head>
<body><main><b>@yield('code')</b><h1>@yield('title')</h1><p>@yield('message')</p><a href="/">Ke beranda</a></main></body>
</html>
