<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\AuditLog;
use App\Models\ChatMessage;
use App\Models\Fighter;
use App\Services\Game\Calendar;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

/** Pengumuman Mading dan moderasi chat. */
class ContentController extends Controller
{
    public function announcements()
    {
        return view('admin.announcements', ['items' => Announcement::orderByDesc('pinned')->orderByDesc('id')->paginate(30)]);
    }

    public function editAnnouncement(?Announcement $announcement = null)
    {
        return view('admin.announcement', ['a' => $announcement ?? new Announcement(['published_at' => now()])]);
    }

    public function saveAnnouncement(Request $request, Calendar $calendar, ?Announcement $announcement = null)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'body' => ['required', 'string', 'max:5000'],
            'pinned' => ['nullable'],
            'publish' => ['required', 'in:now,draft,at'],
            'publish_at' => ['nullable', 'required_if:publish,at', 'date'],
        ]);
        $a = $announcement ?? new Announcement;
        $a->fill([
            'title' => $data['title'],
            'body' => $data['body'],
            'pinned' => $request->boolean('pinned'),
            'published_at' => match ($data['publish']) {
                'now' => $a->published_at && $a->published_at->isPast() ? $a->published_at : now(),
                'draft' => null,
                'at' => CarbonImmutable::parse($data['publish_at'], $calendar->tz())->utc(),
            },
        ]);
        $a->admin_id ??= Auth::guard('admin')->id();
        $a->save();
        Cache::forget('mading:v1');
        AuditLog::record('announcement_saved', $a, ['title' => $a->title], 'admin', Auth::guard('admin')->id());

        return redirect()->route('admin.announcements')->with('ok', 'Pengumuman tersimpan.');
    }

    public function deleteAnnouncement(Announcement $announcement)
    {
        AuditLog::record('announcement_deleted', $announcement, ['title' => $announcement->title], 'admin', Auth::guard('admin')->id());
        $announcement->delete();
        Cache::forget('mading:v1');

        return redirect()->route('admin.announcements')->with('ok', 'Pengumuman dihapus.');
    }

    public function chat(Request $request)
    {
        $q = ChatMessage::with('fighter:id,instagram_username,muted_until')->withCount('reports');
        if ($request->query('filter') === 'hidden') {
            $q->whereNotNull('hidden_at');
        } elseif ($request->query('filter') === 'reported') {
            $q->has('reports');
        }
        if ($room = $request->query('room')) {
            $q->where('room', $room);
        }

        return view('admin.chat', ['messages' => $q->latest('id')->paginate(50)->withQueryString()]);
    }

    public function chatAction(Request $request, ChatMessage $message)
    {
        $admin = Auth::guard('admin')->user();
        switch ($request->input('action')) {
            case 'hide':
                $message->forceFill(['hidden_at' => now(), 'hidden_reason' => 'admin'])->save();
                break;
            case 'unhide':
                $message->forceFill(['hidden_at' => null, 'hidden_reason' => null])->save();
                break;
            case 'mute':
                Fighter::whereKey($message->fighter_id)->update(['muted_until' => now()->addDay()]);
                $message->forceFill(['hidden_at' => $message->hidden_at ?? now(), 'hidden_reason' => $message->hidden_reason ?? 'admin'])->save();
                break;
            default:
                return back();
        }
        AuditLog::record('chat_'.$request->input('action'), $message, ['body' => $message->body], 'admin', $admin->id);

        return back()->with('ok', 'Tersimpan.');
    }
}
