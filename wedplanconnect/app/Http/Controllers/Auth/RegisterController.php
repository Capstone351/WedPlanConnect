<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use App\Models\User;
use App\Notifications\StaffRegistered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * Self-registration. Couple-Clients (Scope 1.1) can start planning straight away; Wedding Planners and
 * Suppliers can also sign up, but wait for the Admin to approve them before they can log in.
 */
class RegisterController extends Controller
{
    /** Who can sign up here, with the label shown on the form. */
    public const ACCOUNT_TYPES = [
        'client' => 'Couple',
        'planner' => 'Wedding Planner',
        'vendor' => 'Supplier',
    ];

    public function show(Request $request): View
    {
        return view('auth.register', [
            'accountType' => array_key_exists((string) $request->query('as'), self::ACCOUNT_TYPES) ? $request->query('as') : 'client',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        // Compare emails case-insensitively ("Ana@Example.com" is the same account as "ana@example.com").
        $request->merge(['email' => strtolower(trim((string) $request->input('email')))]);

        $accountType = $request->validate(['account_type' => ['nullable', Rule::in(array_keys(self::ACCOUNT_TYPES))]])['account_type'] ?? 'client';
        $onlyFor = fn (string $type, array $rules) => $accountType === $type ? $rules : ['exclude'];

        $data = $request->validate([
            // One person per account: reject "Ana & Miguel", "Ana and Miguel", "Ana / Miguel", "Ana + Miguel".
            'name' => ['required', 'string', 'max:150', 'not_regex:/[&\/+]|\s(and|at)\s/i'],
            ...array_map(fn (array $rules) => $onlyFor('client', $rules), User::coupleNameRules()),
            // Any email provider (one-time PINs and notices are sent there).
            'email' => ['required', 'email', 'max:150', 'unique:users,email'],
            // Philippine mobile number: 11 digits starting with 09.
            'phone' => ['required', 'regex:/^09\d{9}$/'],
            'bio' => $onlyFor('planner', ['nullable', 'string', 'max:500']),
            'business_name' => $onlyFor('vendor', ['required', 'string', 'max:150']),
            'business_category' => $onlyFor('vendor', ['required', Rule::in(Supplier::CATEGORIES)]),
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
            'privacy' => ['accepted'],
        ], [
            'name.required' => $accountType === 'client' ? "Please enter the first contact person's full name." : 'Please enter your full name.',
            'name.not_regex' => "Please enter one person's name only.",
            'email.unique' => 'An account with this email address already exists. Try logging in instead.',
            'phone.required' => 'Please enter your mobile number.',
            'phone.regex' => 'The mobile number must start with 09 and have exactly 11 digits, e.g. 09171234567.',
            'business_name.required' => 'Please enter your business name.',
            'business_category.required' => 'Please choose what your business offers.',
            'privacy.accepted' => 'Please agree to the data privacy notice to continue.',
            ...User::coupleNameMessages(),
        ]);

        $account = Arr::except($data, ['privacy', 'business_name', 'business_category']);

        if ($accountType === 'client') {
            return $this->startPlanning($request, User::create([...$account, 'role' => 'client']));
        }

        $user = User::create([...$account, 'role' => $accountType]);
        $user->forceFill([
            'awaiting_approval' => true,
            // A supplier's catalog entry is only created once the Admin approves them.
            'pending_supplier' => $accountType === 'vendor'
                ? ['name' => $data['business_name'], 'category' => $data['business_category']]
                : null,
        ])->save();

        Notification::send(User::where('role', 'admin')->get(), new StaffRegistered($user));

        return redirect()->route('login')->with('status', 'Thank you for signing up as a '.self::ACCOUNT_TYPES[$accountType]
            .'! An administrator will review your account. We will email you at '.$user->email.' once it is approved, and then you can log in.');
    }

    /** Couples are logged in straight away and asked to verify their email. */
    private function startPlanning(Request $request, User $user): RedirectResponse
    {
        $user->forceFill(['last_login_at' => now()])->save();

        $user->sendEmailVerificationNotification();

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('client.dashboard')->with('status', "Welcome to WedPlanConnect! We've emailed you a link to verify your email address.");
    }
}
