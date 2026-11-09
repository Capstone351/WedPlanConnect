<?php

namespace App\Http\Controllers;

use App\Models\Meeting;
use App\Notifications\MeetingAnswered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Meetings couples asked to have with their planner. The planner confirms or declines each one.
 */
class MeetingController extends Controller
{
    public function index(Request $request): View
    {
        $base = Meeting::visibleTo($request->user())->with('client:id,name,bride_name,groom_name,email,phone', 'planner:id,name');

        return view('meetings.index', [
            'upcoming' => (clone $base)->upcoming()->orderBy('scheduled_at')->get(),
            'past' => (clone $base)->where(fn ($q) => $q->whereNotIn('status', Meeting::OPEN_STATUSES)->orWhere('scheduled_at', '<', now()))
                ->orderByDesc('scheduled_at')->limit(30)->get(),
        ]);
    }

    public function respond(Request $request, Meeting $meeting): RedirectResponse
    {
        abort_unless(Meeting::visibleTo($request->user())->whereKey($meeting->id)->exists(), 403);

        $data = $request->validate([
            'status' => ['required', Rule::in(['confirmed', 'declined'])],
            'response_note' => ['nullable', 'string', 'max:255'],
        ]);

        if (! $meeting->isOpen() || $meeting->scheduled_at->isPast()) {
            return back()->withErrors(['meeting' => 'This meeting can no longer be changed.']);
        }

        $meeting->update($data + ['responded_at' => now()]);
        $meeting->client->notify(new MeetingAnswered($meeting));

        return back()->with('status', 'Meeting '.$data['status'].'. The couple has been notified.');
    }
}
