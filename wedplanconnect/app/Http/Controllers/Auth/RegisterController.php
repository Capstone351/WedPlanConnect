<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * Couple-Client self-registration (Scope 1.1). Other roles are created by the Admin.
 */
class RegisterController extends Controller
{
    public function show(): View
    {
        return view('auth.register');
    }

    public function store(Request $request): RedirectResponse
    {
        // Compare emails case-insensitively ("Ana@Gmail.com" is the same account as "ana@gmail.com").
        $request->merge(['email' => strtolower(trim((string) $request->input('email')))]);

        $data = $request->validate([
            // One person per account: reject "Ana & Miguel", "Ana and Miguel", "Ana / Miguel", "Ana + Miguel".
            'name' => ['required', 'string', 'max:150', 'not_regex:/[&\/+]|\s(and|at)\s/i'],
            // Gmail addresses only (one-time PINs and notices are sent there).
            'email' => ['required', 'email', 'max:150', 'regex:/^[a-z0-9.+_-]+@gmail\.com$/', 'unique:users,email'],
            // Philippine mobile number: 11 digits starting with 09.
            'phone' => ['required', 'regex:/^09\d{9}$/'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
            'privacy' => ['accepted'],
        ], [
            'name.not_regex' => "Please enter one person's name only. Each person creates their own account.",
            'email.regex' => 'Please use a Gmail address ending in @gmail.com, e.g. anasantos@gmail.com.',
            'email.unique' => 'An account with this Gmail address already exists. Try logging in instead.',
            'phone.required' => 'Please enter your mobile number.',
            'phone.regex' => 'The mobile number must start with 09 and have exactly 11 digits, e.g. 09171234567.',
            'privacy.accepted' => 'Please agree to the data privacy notice to continue.',
        ]);

        $user = User::create([...Arr::except($data, 'privacy'), 'role' => 'client']);
        $user->forceFill(['last_login_at' => now()])->save();

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('client.dashboard')->with('status', 'Welcome to WedPlanConnect! Your planner will link your wedding booking to this account.');
    }
}
