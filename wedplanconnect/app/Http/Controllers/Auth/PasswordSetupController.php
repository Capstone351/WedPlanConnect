<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\AccountInvitation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;

/**
 * People whose account the Admin created choose their own password from the emailed link,
 * so the Admin never knows it. Uses the longer-lived "invites" token broker.
 */
class PasswordSetupController extends Controller
{
    /** Emails a fresh "set your password" link (any earlier link stops working). */
    public static function invite(User $user): void
    {
        $user->notify(new AccountInvitation(Password::broker('invites')->createToken($user)));
    }

    public function edit(Request $request, string $token): View
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => $request->query('email'),
            'setup' => true,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)->letters()->numbers()],
        ]);

        $status = Password::broker('invites')->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                    'failed_attempts' => 0,
                    'locked_until' => null,
                    // Opening the emailed link proves they own the address.
                    'email_verified_at' => $user->email_verified_at ?? now(),
                ])->save();
            }
        );

        return $status === Password::PASSWORD_RESET
            ? redirect()->route('login')->with('status', 'Your password is set. You can now log in.')
            : back()->withErrors(['email' => $status === Password::INVALID_TOKEN
                ? 'This link has expired or was already used. Please ask the administrator to send a new one.'
                : __($status)]);
    }
}
