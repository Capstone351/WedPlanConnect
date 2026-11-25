<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoginController extends Controller
{
    /** Hash checked against when no account matches, so every login attempt takes the same time. */
    private const DUMMY_HASH = '$2y$12$csre/C3JyRmf.ztzwKoGdenH89U3A/FvlOijb7JYK.YI/0iVOpHVa';

    public function show(): View
    {
        return view('auth.login');
    }

    /**
     * UC-01 User Authentication, with account lockout after five consecutive failures.
     */
    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::withTrashed()->where('email', $credentials['email'])->first();

        // Check the password even for unknown emails so response time doesn't reveal which accounts exist.
        $passwordMatches = Hash::check($credentials['password'], $user?->password ?? self::DUMMY_HASH);

        // A wrong password always gets the same message: whether an account exists, is locked, or is
        // deactivated is only revealed to someone who knows its password.
        if (! $user || ! $passwordMatches) {
            if ($user && ! $user->trashed() && ! $user->isLocked()) {
                $this->recordFailure($user);
            }

            throw ValidationException::withMessages(['email' => 'These credentials do not match our records.']);
        }

        if ($user->trashed()) {
            throw ValidationException::withMessages(['email' => 'This account has been deactivated. Please contact the administrator.']);
        }

        if ($user->awaiting_approval) {
            throw ValidationException::withMessages(['email' => "Your {$user->roleLabel()} account is still waiting for the administrator's approval. We'll email you once it's approved."]);
        }

        if ($user->isLocked()) {
            $minutes = (int) ceil(now()->diffInSeconds($user->locked_until) / 60);
            throw ValidationException::withMessages(['email' => "This account is locked for {$minutes} more minute(s) after too many failed attempts. To unlock it now, use \"Forgot password\" to reset your password."]);
        }

        $user->forceFill(['failed_attempts' => 0, 'locked_until' => null, 'last_login_at' => now()])->save();

        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        return redirect()->intended(route($user->dashboardRoute()));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', 'You have been logged out.');
    }

    /** Counts a failed attempt and locks the account at the limit (the owner can unlock it by resetting the password). */
    private function recordFailure(User $user): void
    {
        $attempts = $user->failed_attempts + 1;

        if ($attempts >= User::MAX_FAILED_ATTEMPTS) {
            $user->forceFill(['failed_attempts' => 0, 'locked_until' => now()->addMinutes(User::LOCKOUT_MINUTES)])->save();

            return;
        }

        $user->forceFill(['failed_attempts' => $attempts])->save();
    }
}
