@extends('admin.layout')
@section('title', 'Pengaturan')
@section('content')
<h1>Pengaturan game</h1>
<p class="muted">Nilai di sini mengalahkan <code>config/game.php</code> dan langsung berlaku (cache 60 detik). Centang "bawaan" untuk kembali ke nilai file.</p>
<form method="post" class="stack">
  @csrf
  @foreach ($groups as $group => $items)
    <div class="card"><h2>{{ $group }}</h2><table>
      @foreach ($items as [$key, $label, $type])
        @php($field = str_replace('.', '__', $key))
        @php($val = $current($key))
        <tr>
          <td style="width:45%">{{ $label }}<br><code class="muted">{{ $key }}</code></td>
          <td>
            @if ($type === 'bool')
              <input type="hidden" name="{{ $field }}" value="0"><label class="inline"><input type="checkbox" name="{{ $field }}" value="1" @checked($val)> aktif</label>
            @else
              <input name="{{ $field }}" value="{{ \App\Support\SettingsSchema::display($type, $val) }}" @if(in_array($type, ['int','float'])) inputmode="decimal" style="width:120px" @endif>
            @endif
          </td>
          <td class="muted" style="white-space:nowrap">
            @if (array_key_exists($key, $overridden))
              <label class="inline"><input type="checkbox" name="reset__{{ $field }}" value="1"> bawaan ({{ \App\Support\SettingsSchema::display($type, $default($key)) }})</label>
            @else bawaan @endif
          </td>
        </tr>
      @endforeach
    </table></div>
  @endforeach
  <div><button class="pri">Simpan pengaturan</button></div>
</form>
@endsection
