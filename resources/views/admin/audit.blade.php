@extends('admin.layout')
@section('title', 'Audit')
@section('content')
@php($tz = app(\App\Services\Game\Calendar::class)->tz())
<h1>Log audit</h1>
<form class="row card" method="get"><input name="action" value="{{ request('action') }}" placeholder="awalan aksi, mis. admin_ atau login"><button>Saring</button></form>
<div class="card"><table>
  <tr><th>Waktu (WIB)</th><th>Aksi</th><th>Pelaku</th><th>Subjek</th><th>Data</th></tr>
  @foreach ($logs as $l)
    <tr><td class="muted" style="white-space:nowrap">{{ $l->created_at->setTimezone($tz)->format('d/m H:i:s') }}</td><td>{{ $l->action }}</td>
      <td>{{ $l->actor_type === 'admin' ? ($admins[$l->actor_id] ?? 'admin#'.$l->actor_id) : $l->actor_type.($l->actor_id ? '#'.$l->actor_id : '') }}</td>
      <td>{{ $l->subject_type }}{{ $l->subject_id ? '#'.$l->subject_id : '' }}</td>
      <td><code>{{ \Illuminate\Support\Str::limit(json_encode($l->data, JSON_UNESCAPED_UNICODE), 160) }}</code></td></tr>
  @endforeach
</table>{{ $logs->links('admin.pager') }}</div>
@endsection
