<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Meeting;
use App\Models\User;
use App\Notifications\MeetingCancelled;
use App\Notifications\MeetingRequested;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Couples ask for a meeting with their Wedding Planner (their booking's planner,
 * or the planner they chose before booking). The planner confirms or declines it.
 */
class MeetingController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $meetings = Meeting::visibleTo($user)->with('planner:id,name')->orderByDesc('scheduled_at')->get();

        return view('client.meetings', [
            'planner' => $this->plannerFor($user),
            'upcoming' => $meetings->filter(fn (Meeting $m) => $m->isOpen() && $m->scheduled_at->isFuture())->sortBy('scheduled_at'),
            'past' => $meetings->reject(fn (Meeting $m) => $m->isOpen() && $m->scheduled_at->isFuture()),
            'modes' => Meeting::MODES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'date' => ['required', 'date', 'after_or_equal:today'],
            'time' => ['required', 'date_format:H:i'],
            'mode' => ['required', Rule::in(array_keys(Meeting::MODES))],
            'location' => ['nullable', 'string', 'max:200'],
            'agenda' => ['nullable', 'string', 'max:500'],
        ], [
            'date.required' => 'Please choose a meeting date.',
            'date.after_or_equal' => 'The meeting date cannot be in the past.',
            'time.required' => 'Please choose a meeting time.',
        ]);

        $at = Carbon::parse($data['date'].' '.$data['time']);

        $meeting = DB::transaction(function () use ($request, $data, $at) {
            // Lock the couple's row so a double click can't send two requests.
            $user = User::lockForUpdate()->findOrFail($request->user()->id);
            $planner = $this->plannerFor($user);
            $this->guardSlot($planner, $at);

            return Meeting::create([
                'client_id' => $user->id,
                'planner_id' => $planner->id,
                'scheduled_at' => $at,
                'mode' => $data['mode'],
                'location' => $data['location'] ?? null,
                'agenda' => $data['agenda'] ?? null,
                'status' => 'requested',
            ]);
        });

        $meeting->planner->notify(new MeetingRequested($meeting));

        return redirect()->route('client.meetings')
            ->with('status', "Meeting request sent to {$meeting->planner->name} for {$meeting->when()}. You'll be notified when they reply.");
    }

    public function cancel(Request $request, Meeting $meeting): RedirectResponse
    {
        abort_unless($meeting->client_id === $request->user()->id, 403);

        if (! $meeting->isOpen() || $meeting->scheduled_at->isPast()) {
            return back()->withErrors(['meeting' => 'This meeting can no longer be cancelled.']);
        }

        $meeting->update(['status' => 'cancelled']);
        $meeting->planner->notify(new MeetingCancelled($meeting));

        return back()->with('status', 'Meeting cancelled. Your planner has been told.');
    }

    /** The booking's planner once booked, otherwise the planner the couple chose. */
    private function plannerFor(User $user): ?User
    {
        $planner = $user->clientBookings()->active()->orderBy('event_date')->first()?->planner ?? $user->preferredPlanner;

        return $planner && ! $planner->trashed() ? $planner : null;
    }

    private function guardSlot(?User $planner, Carbon $at): void
    {
        $fail = fn (string $message) => throw ValidationException::withMessages(['time' => $message]);

        if (! $planner) {
            throw ValidationException::withMessages(['meeting' => 'Please choose your Wedding Planner first.']);
        }

        if ($at->isPast()) {
            $fail('Please choose a time later than now.');
        }

        if (Booking::plannerIdsBookedOn($at)->contains($planner->id)) {
            $fail("{$planner->name} has a wedding on ".$at->format('F j, Y').'. Please pick another day.');
        }

        if (Meeting::findClash($planner->id, $at)) {
            $fail("{$planner->name} already has a meeting around ".$at->format('g:i A').' that day. Please pick another time.');
        }
    }
}
