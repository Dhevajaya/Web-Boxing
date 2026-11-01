@php
    $game = app(\App\Support\GameConfig::class);
    $brand = $game->get('brand');
@endphp
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <meta name="description" content="{{ $brand['tagline'] }}">
  <meta name="theme-color" content="#0f1115">
  <meta property="og:site_name" content="{{ $brand['name'] }}">
  <meta property="og:title" content="{{ $brand['name'] }}">
  <meta property="og:description" content="{{ $brand['tagline'] }}">
  <title>{{ isset($pageTitle) ? $pageTitle.' · ' : '' }}{{ $brand['name'] }}</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Anton&family=Manrope:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
  @vite(['resources/css/app.css', 'resources/js/app.js'])
  <script>window.GAME = @json($game->publicConfig());</script>
</head>
<body>
  <div class="shell">
    <header class="top">
      <a href="/" class="brand" data-link aria-label="Ke beranda">
        <span class="brand-mark" aria-hidden="true"></span>
        <span class="brand-name">{{ $brand['name'] }}</span>
      </a>
      <button class="btn btn-outline btn-sm" id="loginBtn" type="button">Masuk</button>
    </header>

    <main id="view" class="view" aria-live="polite"></main>

    <footer class="foot">
      <span>&copy; {{ date('Y') }} {{ $brand['name'] }}</span>
      <a href="/privasi" data-link>Privasi</a>
    </footer>
  </div>

  <nav class="tabbar" aria-label="Navigasi utama">
    <a href="/" data-link data-tab="home"><span class="ic" aria-hidden="true">⌂</span>Beranda</a>
    <a href="/mading" data-link data-tab="mading"><span class="ic" aria-hidden="true">▤</span>Mading</a>
    <a href="/latihan" data-link data-tab="latihan" class="tab-main"><span class="ic" aria-hidden="true">✊</span>Latihan</a>
    <a href="/profil" data-link data-tab="profil"><span class="ic" aria-hidden="true">◉</span>Profil</a>
    <a href="/markas" data-link data-tab="markas"><span class="ic" aria-hidden="true">✉</span>Markas</a>
  </nav>
</body>
</html>
