<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\User;
use App\Notifications\BookingRequested;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Couples send their wedding details to their chosen planner as a pending booking,
 * so the planner no longer has to create it by hand. The price comes from their chosen package.
 */
class BookingRequestController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'venue' => ['required', 'string', 'max:200'],
        ], [
            'venue.required' => 'Please enter your wedding venue.',
        ]);

        $booking = DB::transaction(function () use ($request, $data) {
            // Lock the couple's row so a double click can't send two bookings.
            $user = User::lockForUpdate()->findOrFail($request->user()->id);
            $this->guardReady($user, $data['venue']);

            $user->forceFill(['wedding_venue' => $data['venue']])->save();

            return Booking::create([
                'planner_id' => $user->preferred_planner_id,
                'client_id' => $user->id,
                'client_name' => $user->coupleNames() ?? $user->name,
                'contact_number' => $user->phone,
                'event_date' => $user->wedding_date,
                'venue' => $data['venue'],
                'package' => $user->wedding_package,
                'total_amount' => Booking::packagePrice($user->wedding_package),
                'status' => 'pending',
            ]);
        });

        $booking->planner->notify(new BookingRequested($booking));

        return redirect()->route('client.wedding-date')
            ->with('status', "Your booking was sent to {$booking->planner->name}. They'll confirm it soon.");
    }

    /** Everything the planner needs must be filled in, and the slot must still be free. */
    private function guardReady(User $user, string $venue): void
    {
        $fail = fn (string $message) => throw ValidationException::withMessages(['booking' => $message]);

        if ($user->clientBookings()->active()->exists()) {
            $fail('You already have a booking. Please contact your planner about any changes.');
        }

        if (! $user->preferredPlanner || $user->preferredPlanner->trashed()) {
            $fail('Please choose your Wedding Planner first.');
        }

        if (! $user->wedding_date || ! $user->wedding_date->isFuture()) {
            $fail('Please save a wedding date in the future first.');
        }

        if (Booking::packagePrice($user->wedding_package) === null) {
            $fail('Please choose your wedding package first.');
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
