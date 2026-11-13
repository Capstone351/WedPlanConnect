<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Confirms a couple owns their email before they can hold a planner's date or ask for meetings.
 * The link is signed and expires, so it works without being logged in (e.g. opened on a phone).
 */
class EmailVerificationController extends Controller
{
    public function verify(Request $request, int $id, string $hash): RedirectResponse
    {
        $user = User::findOrFail($id);
        abort_unless(hash_equals(sha1($user->getEmailForVerification()), $hash), 403);

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }

        return redirect()->route($request->user()?->is($user) ? 'client.dashboard' : 'login')
            ->with('status', 'Thank you! Your email address is verified.');
    }

    public function send(Request $request): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return back()->with('status', 'Your email address is already verified.');
        }

        $request->user()->sendEmailVerificationNotification();

        return back()->with('status', 'A new verification link has been sent to '.$request->user()->email.'.');
    }
}
