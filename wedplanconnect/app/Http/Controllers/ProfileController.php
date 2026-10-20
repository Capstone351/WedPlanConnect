<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        return view('account.edit', ['user' => $request->user()]);
    }

    /** Couples can correct the bride's and groom's names shown on their dashboard. */
    public function updateCouple(Request $request): RedirectResponse
    {
        abort_unless($request->user()->role === 'client', 403);

        $request->user()->update($request->validate(User::coupleNameRules(), User::coupleNameMessages()));

        return back()->with('status', "The couple's names have been updated.");
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ]);

        $request->user()->update(['password' => $data['password']]);

        return back()->with('status', 'Your password has been changed.');
    }
}
