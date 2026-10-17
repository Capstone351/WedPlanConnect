<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
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

        return view('client.planner', [
            'planners' => User::where('role', 'planner')
                ->withCount([
                    'plannedBookings as weddings_done' => fn ($q) => $q->where('status', 'completed'),
                    'plannedBookings as weddings_upcoming' => fn ($q) => $q->upcoming(),
                ])
                ->orderBy('name')->get(),
            'chosenId' => $user->preferred_planner_id,
            'booking' => $user->clientBookings()->active()->with('planner')->orderBy('event_date')->first(),
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

        $user->forceFill(['preferred_planner_id' => $data['planner_id']])->save();
        $planner = User::find($data['planner_id']);

        return redirect()->route('client.planner')->with('status', "You chose {$planner->name} as your Wedding Planner. They'll be assigned when your booking is created.");
    }
}
