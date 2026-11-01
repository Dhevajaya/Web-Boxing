@extends('admin.layout')
@section('title', 'Chat')
@section('content')
@php($tz = app(\App\Services\Game\Calendar::class)->tz())
<h1>Moderasi chat</h1>
<form class="row card" method="get">
  <select name="filter">
    <option value="">Semua</option>
    <option value="reported" @selected(request('filter') === 'reported')>Dilaporkan</option>
    <option value="hidden" @selected(request('filter') === 'hidden')>Disembunyikan</option>
  </select>
  <input name="room" value="{{ request('room') }}" placeholder="ruang (alun, sasana:naga)">
  <button>Saring</button>
</form>
<div class="card"><table>
  <tr><th>Waktu</th><th>Ruang</th><th>Dari</th><th>Pesan</th><th>Lapor</th><th></th></tr>
  @forelse ($messages as $m)
    <tr>
      <td class="muted">{{ $m->created_at->setTimezone($tz)->format('d/m H:i') }}</td>
      <td>{{ $m->room }}</td>
      <td><a href="{{ route('admin.fighters.show', $m->fighter_id) }}">{{ '@'.$m->fighter?->instagram_username }}</a></td>
      <td @class(['muted' => $m->hidden_at])>{{ $m->body }} @if($m->hidden_at)<span class="pill bad">{{ $m->hidden_reason }}</span>@endif</td>
      <td>{{ $m->reports_count }}</td>
      <td class="row">
        <form method="post" action="{{ route('admin.chat.action', $m) }}">@csrf<input type="hidden" name="action" value="{{ $m->hidden_at ? 'unhide' : 'hide' }}"><button>{{ $m->hidden_at ? 'Tampilkan' : 'Sembunyikan' }}</button></form>
        <form method="post" action="{{ route('admin.chat.action', $m) }}">@csrf<input type="hidden" name="action" value="mute"><button class="danger">Bisu 24j</button></form>
      </td>
    </tr>
  @empty <tr><td colspan="6" class="muted">Tidak ada pesan.</td></tr> @endforelse
</table>{{ $messages->links('admin.pager') }}</div>
@endsection
