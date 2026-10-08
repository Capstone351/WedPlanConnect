<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Http\Request;
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
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:150', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:20'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
            'privacy' => ['accepted'],
        ], [
            'privacy.accepted' => 'Please agree to the data privacy notice to continue.',
        ]);

        $user = User::create([...Arr::except($data, 'privacy'), 'role' => 'client']);
        $user->forceFill(['last_login_at' => now()])->save();

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('client.dashboard')->with('status', 'Welcome to WedPlanConnect! Your planner will link your wedding booking to this account.');
    }
}
