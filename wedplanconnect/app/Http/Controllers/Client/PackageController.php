<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Couples choose a wedding package that fits their budget. When no package fits,
 * they are asked to book a meeting with their planner instead.
 */
class PackageController extends Controller
{
    public function edit(Request $request): View
    {
        $user = $request->user();
        $packages = Booking::packages();
        $budget = $user->budget ? (float) $user->budget->total_amount : null;

        return view('client.packages', [
            'packages' => $packages,
            'inclusions' => config('wedplan.package_inclusions'),
            'budget' => $budget,
            'chosen' => $user->wedding_package,
            'booking' => $user->clientBookings()->active()->first(),
            'belowAll' => $budget !== null && $budget < min(array_column($packages, 'price')),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->clientBookings()->active()->exists()) {
            return back()->withErrors(['package' => 'Your booking has been made, so please ask your planner to change your package.']);
        }

        $data = $request->validate(['package' => ['required', Rule::in(array_keys(Booking::packages()))]]);

        if (! $user->budget) {
            return redirect()->route('client.budget')->withErrors(['package' => 'Please set your budget first, so we can show which packages fit.']);
        }

        $price = Booking::packagePrice($data['package']);
        if ($price > (float) $user->budget->total_amount) {
            return back()->withErrors(['package' => "{$data['package']} is ₱".number_format($price - (float) $user->budget->total_amount, 0).' above your budget. Choose another package or book a meeting with your planner.']);
        }

        $user->forceFill(['wedding_package' => $data['package']])->save();

        return redirect()->route('client.package')->with('status', "You chose {$data['package']} (₱".number_format($price, 0).'). It will be included when you send your booking.');
    }
}
