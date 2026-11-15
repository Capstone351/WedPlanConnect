<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\User;
use App\Notifications\BookingRequested;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Couples send their wedding details to their chosen planner as a pending booking,
 * so the planner no longer has to create it by hand. The price comes from their chosen package;
 * the package and venue may be left for a meeting, and the planner fills them in before confirming.
 * Either way the couple then books an in-person meeting, where the down payment is made.
 */
class BookingRequestController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            // Optional: the venue and package can be agreed with the planner at a meeting.
            'venue' => ['nullable', 'string', 'max:200'],
        ]);
        $venue = $data['venue'] ?? null;

        $send = fn () => DB::transaction(function () use ($request, $venue) {
            // Lock the couple's row so a double click can't send two bookings.
            $user = User::lockForUpdate()->findOrFail($request->user()->id);
            $this->guardReady($user, $venue);

            if ($venue) {
                $user->forceFill(['wedding_venue' => $venue])->save();
            }

            return Booking::create([
                'planner_id' => $user->preferred_planner_id,
                'client_id' => $user->id,
                'client_name' => $user->coupleNames() ?? $user->name,
                'contact_number' => $user->phone,
                'event_date' => $user->wedding_date,
                'venue' => $venue,
                'package' => $user->wedding_package,
                'total_amount' => Booking::packagePrice($user->wedding_package) ?? 0,
                'status' => 'pending',
            ]);
        });

        // Lock the wedding date too, so two couples sending for the same day are checked one at a time.
        $date = $request->user()->wedding_date;
        $booking = $date ? Booking::withDateLock($date->toDateString(), $send) : $send();

        $booking->planner->notify(new BookingRequested($booking));

        // Every couple meets the planner next: the down payment is made at the meeting, then the booking is confirmed.
        return redirect()->route('client.meetings', ['mode' => 'in_person', 'agenda' => $booking->meetingAgenda()])
            ->with('status', "Your booking was sent to {$booking->planner->name}. Next, book an in-person meeting with them"
                .($booking->missingBeforeConfirm() ? ' to agree on the '.Arr::join($booking->missingBeforeConfirm(), ', ', ' and ').' and' : ' to')
                .' pay your down payment. They confirm your booking after that.');
    }

    /** Everything the planner needs must be filled in, and the slot must still be free. */
    private function guardReady(User $user, ?string $venue): void
    {
        $fail = fn (string $message) => throw ValidationException::withMessages(['booking' => $message]);

        // Stops throwaway accounts from holding a planner's date.
        if (! $user->hasVerifiedEmail()) {
            $fail('Please verify your email address before sending your booking. Use the link we emailed you, or send a new one from My Wedding.');
        }

        if ($user->clientBookings()->active()->exists()) {
            $fail('You already have a booking. Please contact your planner about any changes.');
        }

        if (! $user->preferredPlanner || $user->preferredPlanner->trashed()) {
            $fail('Please choose your Wedding Planner first.');
        }

        if (! $user->wedding_date || ! $user->wedding_date->isFuture()) {
            $fail('Please save a wedding date in the future first.');
        }

        if (! $user->phone) {
            $fail('Please add your mobile number in My Account first, so your planner can reach you.');
        }

        if (Booking::plannerIdsBookedOn($user->wedding_date)->contains($user->preferred_planner_id)) {
            $fail("{$user->preferredPlanner->name} already has a wedding on ".$user->wedding_date->format('F j, Y').'. Please pick another date or planner.');
        }

        // Same double-booking rule the planner's form uses, without revealing the other couple.
        if (Booking::findConflict($user->wedding_date->toDateString(), $venue)) {
            $fail('That date is already booked'.(config('wedplan.conflict_scope') === 'date_venue' ? ' at this venue' : '').'. Please pick another date or venue.');
        }
    }
}
