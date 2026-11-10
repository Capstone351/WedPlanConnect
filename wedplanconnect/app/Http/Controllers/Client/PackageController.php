<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * After setting their wedding date, couples choose a wedding package. When no package
 * fits their budget, they book an in-person meeting with their planner to discuss it.
 */
class PackageController extends Controller
{
    /** Pre-filled reason for the meeting when no package fits. */
    public const MEETING_AGENDA = "Our budget doesn't fit the packages. We'd like to meet in person to discuss it.";

    public function edit(Request $request): View
    {
        $user = $request->user();

        return view('client.packages', [
            'packages' => Booking::packages(),
            'inclusions' => config('wedplan.package_inclusions'),
            'chosen' => $user->wedding_package,
            'hasDate' => (bool) $user->wedding_date,
            'booking' => $user->clientBookings()->active()->first(),
            'meetingUrl' => route('client.meetings', ['agenda' => self::MEETING_AGENDA, 'mode' => 'in_person']),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->clientBookings()->active()->exists()) {
            return back()->withErrors(['package' => 'Your booking has been made, so please ask your planner to change your package.']);
        }

        if (! $user->wedding_date) {
            return redirect()->route('client.wedding-date')->withErrors(['package' => 'Please set your wedding date first, then choose your package.']);
        }

        $data = $request->validate(['package' => ['required', Rule::in(array_keys(Booking::packages()))]]);

        $user->forceFill(['wedding_package' => $data['package']])->save();

        return redirect()->route('client.package')
            ->with('status', "You chose {$data['package']} (₱".number_format(Booking::packagePrice($data['package']), 0).'). It will be included when you send your booking.');
    }
}
