@extends('admin.layout')
@section('title', 'Pengumuman')
@section('content')
@php($tz = app(\App\Services\Game\Calendar::class)->tz())
<div class="row"><h1>Pengumuman</h1><a class="btn pri" href="{{ route('admin.announcements.new') }}">+ Baru</a></div>
<div class="card"><table>
  <tr><th>Judul</th><th>Status</th><th>Terbit</th><th></th></tr>
  @forelse ($items as $a)
    <tr>
      <td>@if($a->pinned)📌 @endif<a href="{{ route('admin.announcements.edit', $a) }}">{{ $a->title }}</a></td>
      <td>@if(!$a->published_at)<span class="pill">draf</span>@elseif($a->published_at->isFuture())<span class="pill">terjadwal</span>@else<span class="pill ok">tayang</span>@endif</td>
      <td class="muted">{{ $a->published_at?->setTimezone($tz)->translatedFormat('d M Y H:i') ?? '-' }}</td>
      <td><form method="post" action="{{ route('admin.announcements.delete', $a) }}" onsubmit="return confirm('Hapus pengumuman ini?')">@csrf<button class="danger">Hapus</button></form></td>
    </tr>
  @empty <tr><td colspan="4" class="muted">Belum ada pengumuman.</td></tr> @endforelse
</table>{{ $items->links('admin.pager') }}</div>
@endsection
