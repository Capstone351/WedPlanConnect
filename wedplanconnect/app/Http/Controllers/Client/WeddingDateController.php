<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Couples set their preferred wedding date (and an optional backup date and venue)
 * so their Wedding Planner knows. Once a booking exists, the booked date is shown instead.
 */
class WeddingDateController extends Controller
{
    public function edit(Request $request): View
    {
        $user = $request->user();

        return view('client.wedding-date', [
            'user' => $user,
            'booking' => $user->clientBookings()->active()->with('planner')->orderBy('event_date')->first(),
            'plannerBusyDates' => $this->plannerBusyDates($user),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($booking = $user->clientBookings()->active()->first()) {
            return back()->withErrors(['wedding_date' => 'Your wedding is already booked for '.$booking->event_date->format('F j, Y').'. To change the date, please contact your planner, '.$booking->planner->name.'.']);
        }

        $data = $request->validate([
            'wedding_date' => ['required', 'date', 'after:today'],
            'wedding_date_alt' => ['nullable', 'date', 'after:today', 'different:wedding_date'],
            'wedding_venue' => ['nullable', 'string', 'max:200'],
        ], [
            'wedding_date.required' => 'Please choose your preferred wedding date.',
            'wedding_date.after' => 'Your wedding date must be in the future.',
            'wedding_date_alt.after' => 'The backup date must be in the future.',
            'wedding_date_alt.different' => 'The backup date should be different from your preferred date.',
        ]);

        // The couple's chosen planner must be free on the dates they pick.
        $busy = $this->plannerBusyDates($user)->map(fn (Carbon $d) => $d->toDateString());
        foreach (['wedding_date' => 'that date', 'wedding_date_alt' => 'your backup date'] as $field => $label) {
            if (! empty($data[$field]) && $busy->contains(Carbon::parse($data[$field])->toDateString())) {
                return back()->withInput()->withErrors([$field => "Your planner, {$user->preferredPlanner->name}, already has a wedding on {$label}. Please pick another date, or choose a different planner."]);
            }
        }

        $user->forceFill($data)->save();

        $message = 'Your wedding date is saved: '.$user->wedding_date->format('l, F j, Y').'.';
        $message .= $user->preferred_planner_id
            ? ' '.$user->preferredPlanner->name.' can now see it.'
            : ' Your Wedding Planner will see it.';

        return redirect()->route('client.wedding-date')->with('status', $message);
    }

    /**
     * How busy FMT is on a date, without revealing anyone's details.
     * Used for the live hint while the couple picks a date.
     */
    public function availability(Request $request): JsonResponse
    {
        $request->validate(['date' => ['required', 'date'], 'venue' => ['nullable', 'string', 'max:200']]);
        $date = Carbon::parse($request->input('date'))->toDateString();

        $sameDay = Booking::active()->whereDate('event_date', $date);
        $count = (clone $sameDay)->count();
        $venueTaken = $request->filled('venue')
            && (clone $sameDay)->whereRaw('LOWER(TRIM(venue)) = ?', [mb_strtolower(trim($request->input('venue')))])->exists();

        return response()->json([
            'weddings' => $count,
            'venue_taken' => $venueTaken,
            'planner_busy' => $this->plannerBusyDates($request->user())->contains(fn (Carbon $d) => $d->toDateString() === $date),
            'past' => $date <= today()->toDateString(),
        ]);
    }

    /**
     * Upcoming dates on which the couple's chosen planner already has a wedding (dates only, no couple details).
     *
     * @return Collection<int, Carbon>
     */
    private function plannerBusyDates(User $user): Collection
    {
        if (! $user->preferred_planner_id) {
            return collect();
        }

        return Booking::active()->where('planner_id', $user->preferred_planner_id)
            ->whereDate('event_date', '>', today())
            ->orderBy('event_date')->pluck('event_date')->unique(fn (Carbon $d) => $d->toDateString())->values();
    }
}
