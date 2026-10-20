<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Couples choose their preferred Wedding Planner. The choice is used when FMT creates
 * their booking. Once a booking exists, its assigned planner is shown instead.
 */
class PlannerChoiceController extends Controller
{
    public function edit(Request $request): View
    {
        $user = $request->user();
        $booking = $user->clientBookings()->active()->with('planner')->orderBy('event_date')->first();

        return view('client.planner', [
            'planners' => User::where('role', 'planner')
                ->withCount([
                    'plannedBookings as weddings_done' => fn ($q) => $q->where('status', 'completed'),
                    'plannedBookings as weddings_upcoming' => fn ($q) => $q->upcoming(),
                ])
                ->orderBy('name')->get(),
            'chosenId' => $user->preferred_planner_id,
            'booking' => $booking,
            'weddingDate' => $user->wedding_date,
            // Planners already handling another wedding on the couple's date can't be chosen.
            'bookedIds' => ! $booking && $user->wedding_date ? Booking::plannerIdsBookedOn($user->wedding_date) : collect(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($booking = $user->clientBookings()->active()->first()) {
            return back()->withErrors(['planner_id' => "Your wedding is already being handled by {$booking->planner->name}. To change planners, please contact ".config('wedplan.business_name').'.']);
        }

        $data = $request->validate([
            'planner_id' => ['required', Rule::exists('users', 'id')->where('role', 'planner')->whereNull('deleted_at')],
        ], ['planner_id.required' => 'Please choose a planner.']);

        $planner = User::find($data['planner_id']);

        if ($user->wedding_date && Booking::plannerIdsBookedOn($user->wedding_date)->contains($planner->id)) {
            return back()->withErrors(['planner_id' => "{$planner->name} already has a wedding on ".$user->wedding_date->format('F j, Y').'. Please choose another planner.']);
        }

        $user->forceFill(['preferred_planner_id' => $planner->id])->save();

        return redirect()->route('client.planner')->with('status', "You chose {$planner->name} as your Wedding Planner. They'll be assigned when your booking is created.");
    }
}
