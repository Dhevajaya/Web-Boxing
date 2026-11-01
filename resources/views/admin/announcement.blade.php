@extends('admin.layout')
@section('title', $a->exists ? 'Ubah pengumuman' : 'Pengumuman baru')
@section('content')
@php($tz = app(\App\Services\Game\Calendar::class)->tz())
@php($mode = old('publish', ! $a->published_at ? 'draft' : ($a->published_at->isFuture() ? 'at' : 'now')))
<div class="row"><a href="{{ route('admin.announcements') }}">← Pengumuman</a><h1>{{ $a->exists ? 'Ubah' : 'Baru' }}</h1></div>
<form method="post" class="card stack" action="{{ $a->exists ? route('admin.announcements.edit', $a) : route('admin.announcements.new') }}">
  @csrf
  <label>Judul <input name="title" value="{{ old('title', $a->title) }}" maxlength="120" required></label>
  <label>Isi <span class="muted">(teks polos; baris kosong = paragraf baru; tautan https:// otomatis bisa diklik)</span>
    <textarea name="body" maxlength="5000" required>{{ old('body', $a->body) }}</textarea></label>
  <label class="inline"><input type="checkbox" name="pinned" value="1" @checked(old('pinned', $a->pinned))> Sematkan di atas</label>
  <div class="row">
    <label class="inline"><input type="radio" name="publish" value="now" @checked($mode === 'now')> Tayangkan sekarang</label>
    <label class="inline"><input type="radio" name="publish" value="at" @checked($mode === 'at')> Jadwalkan</label>
    <input type="datetime-local" name="publish_at" value="{{ old('publish_at', $a->published_at?->setTimezone($tz)->format('Y-m-d\TH:i')) }}"> <span class="muted">WIB</span>
    <label class="inline"><input type="radio" name="publish" value="draft" @checked($mode === 'draft')> Draf</label>
  </div>
  <div><button class="pri">Simpan</button></div>
</form>
@endsection
