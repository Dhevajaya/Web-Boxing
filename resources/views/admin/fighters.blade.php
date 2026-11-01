@extends('admin.layout')
@section('title', 'Petarung')
@section('content')
<h1>Petarung</h1>
<form class="row card" method="get">
  <input name="q" value="{{ request('q') }}" placeholder="cari username">
  <select name="filter">
    <option value="">Pemain</option>
    @foreach (['suspended' => 'Disuspend', 'muted' => 'Dibisukan', 'npc' => 'NPC'] as $k => $v)<option value="{{ $k }}" @selected(request('filter') === $k)>{{ $v }}</option>@endforeach
  </select>
  <button>Cari</button>
</form>
<div class="card">
<table>
  <tr><th>Username</th><th>Status</th><th>Lv / XP</th><th>Verifikasi</th><th>Aktif terakhir</th></tr>
  @forelse ($fighters as $f)
    <tr>
      <td><a href="{{ route('admin.fighters.show', $f) }}">{{ '@'.$f->instagram_username }}</a> {{ $f->sasana?->icon }} @if($f->supporter_tier)<span class="pill">♥ {{ $f->supporter_tier }}</span>@endif</td>
      <td><span @class(['pill', 'ok' => $f->status === 'active', 'bad' => $f->status !== 'active'])>{{ $f->status }}</span> @if($f->muted_until?->isFuture())<span class="pill bad">bisu</span>@endif</td>
      <td>{{ $f->level }} / {{ $f->xp }}</td>
      <td>{!! $f->verified_at ? '<span class="ok">IG ✓</span>' : '<span class="muted">manual/dev</span>' !!}</td>
      <td class="muted">{{ $f->last_active_at?->diffForHumans() ?? '-' }}</td>
    </tr>
  @empty <tr><td colspan="5" class="muted">Tidak ada.</td></tr> @endforelse
</table>
{{ $fighters->links('admin.pager') }}
</div>
@endsection
