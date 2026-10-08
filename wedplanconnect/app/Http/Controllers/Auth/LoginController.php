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

        if ($user?->trashed()) {
            throw ValidationException::withMessages(['email' => 'This account has been deactivated. Please contact the administrator.']);
        }

        if ($user?->isLocked()) {
            $minutes = (int) ceil(now()->diffInSeconds($user->locked_until) / 60);
            throw ValidationException::withMessages(['email' => "Too many failed attempts. This account is locked for {$minutes} more minute(s)."]);
        }

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            $this->recordFailure($user);
            throw ValidationException::withMessages(['email' => 'These credentials do not match our records.']);
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

    private function recordFailure(?User $user): void
    {
        if (! $user) {
            return;
        }

        $attempts = $user->failed_attempts + 1;

        if ($attempts >= User::MAX_FAILED_ATTEMPTS) {
            $user->forceFill(['failed_attempts' => 0, 'locked_until' => now()->addMinutes(User::LOCKOUT_MINUTES)])->save();

            throw ValidationException::withMessages([
                'email' => 'Too many failed attempts. This account is locked for '.User::LOCKOUT_MINUTES.' minutes.',
            ]);
        }

        $user->forceFill(['failed_attempts' => $attempts])->save();
    }
}
