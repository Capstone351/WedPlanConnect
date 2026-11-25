<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Auth\PasswordSetupController;
use App\Http\Controllers\Controller;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * UC-02 Manage User Accounts.
 */
class UserController extends Controller
{
    public function index(Request $request): View
    {
        $users = User::withTrashed()
            ->when($request->filled('q'), function ($query) use ($request) {
                $q = '%'.$request->string('q').'%';
                $query->where(fn ($w) => $w->where('name', 'like', $q)->orWhere('email', 'like', $q));
            })
            ->when($request->filled('role'), fn ($query) => $query->where('role', $request->string('role')))
            ->when($request->input('status') === 'active', fn ($query) => $query->whereNull('deleted_at')->approved())
            ->when($request->input('status') === 'pending', fn ($query) => $query->awaitingApproval())
            ->when($request->input('status') === 'inactive', fn ($query) => $query->whereNotNull('deleted_at'))
            ->when($request->input('status') === 'locked', fn ($query) => $query->where('locked_until', '>', now()))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        $counts = User::approved()->selectRaw('role, count(*) as total')->groupBy('role')->pluck('total', 'role');
        $pendingSignups = User::awaitingApproval()->oldest()->get();

        return view('admin.users.index', compact('users', 'counts', 'pendingSignups'));
    }

    /** ?role=planner or ?role=vendor opens the form with that role already chosen. */
    public function create(Request $request): View
    {
        $user = new User(['role' => in_array($request->query('role'), array_keys(User::ROLES), true) ? $request->query('role') : 'planner']);

        return view('admin.users.form', ['user' => $user, 'suppliers' => $this->linkableSuppliers()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        // Nobody knows this random password: the person chooses their own from the emailed link.
        $user = User::create([...$data, 'password' => Str::random(40)]);
        // The administrator creates these accounts directly, so the email doesn't need confirming.
        $user->markEmailAsVerified();
        $this->linkSupplier($user, $this->supplierIdFor($request, $user));

        PasswordSetupController::invite($user);

        return redirect()->route('admin.users.index')->with('status', "Account for {$user->name} created. We've emailed {$user->email} a link to set their password.");
    }

    /** Sends a new "set your password" link, e.g. when the first one expired. */
    public function invite(User $user): RedirectResponse
    {
        PasswordSetupController::invite($user);

        return back()->with('status', "A new password set-up link has been emailed to {$user->email}.");
    }

    public function edit(User $user): View
    {
        return view('admin.users.form', ['user' => $user, 'suppliers' => $this->linkableSuppliers($user)]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $this->validated($request, $user);

        if (empty($data['password'])) {
            unset($data['password']);
        }

        if ($user->is($request->user()) && $data['role'] !== 'admin') {
            return back()->withErrors(['role' => 'You cannot remove your own admin role.']);
        }

        $user->update($data);
        $this->linkSupplier($user, $this->supplierIdFor($request, $user));

        return redirect()->route('admin.users.index')->with('status', "Account for {$user->name} updated.");
    }

    /** Deactivate = soft delete, preserving history (RA 10173 retention). */
    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($user->is($request->user())) {
            return back()->withErrors(['user' => 'You cannot deactivate your own account.']);
        }

        $user->delete();

        return back()->with('status', "{$user->name} has been deactivated.");
    }

    public function restore(User $user): RedirectResponse
    {
        $user->restore();

        return back()->with('status', "{$user->name} has been reactivated.");
    }

    public function unlock(User $user): RedirectResponse
    {
        $user->forceFill(['failed_attempts' => 0, 'locked_until' => null])->save();

        return back()->with('status', "{$user->name} has been unlocked.");
    }

    private function validated(Request $request, ?User $user = null): array
    {
        return Arr::except($request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')->ignore($user?->id)],
            'phone' => ['nullable', 'string', 'max:20'],
            'role' => ['required', Rule::in(array_keys(User::ROLES))],
            // New accounts get no password here; on edit the Admin may still set one.
            'password' => $user ? ['nullable', 'confirmed', Password::min(8)->letters()->numbers()] : ['exclude'],
            'supplier_id' => ['nullable', 'exists:suppliers,id'],
            'supplier_mode' => ['nullable', Rule::in(['existing', 'new'])],
            'new_supplier_name' => ['exclude_unless:supplier_mode,new', 'exclude_unless:role,vendor', 'required', 'string', 'max:150'],
            'new_supplier_category' => ['exclude_unless:supplier_mode,new', 'exclude_unless:role,vendor', 'required', Rule::in(Supplier::CATEGORIES)],
            'new_supplier_phone' => ['exclude_unless:supplier_mode,new', 'exclude_unless:role,vendor', 'required', 'string', 'max:20', 'regex:/^[0-9+\-\s()]{7,20}$/'],
            'bio' => ['nullable', 'string', 'max:500'],
        ], [
            'new_supplier_name.required' => 'Enter the supplier’s business name.',
            'new_supplier_category.required' => 'Choose the supplier’s category.',
            'new_supplier_phone.required' => 'Enter the supplier’s phone number.',
            'new_supplier_phone.regex' => 'Enter a valid phone number.',
        ]), ['supplier_id', 'supplier_mode', 'new_supplier_name', 'new_supplier_category', 'new_supplier_phone']);
    }

    /** The supplier profile to link a vendor to: a new catalog entry made from the form, or an existing one. */
    private function supplierIdFor(Request $request, User $user): ?int
    {
        if ($user->role !== 'vendor') {
            return null;
        }

        if ($request->input('supplier_mode') === 'new') {
            return Supplier::create([
                'name' => $request->string('new_supplier_name')->trim()->toString(),
                'category' => $request->input('new_supplier_category'),
                'phone' => $request->string('new_supplier_phone')->trim()->toString(),
                'email' => $user->email,
                'availability' => 'available',
            ])->id;
        }

        return $request->integer('supplier_id') ?: null;
    }

    private function linkableSuppliers(?User $user = null)
    {
        return Supplier::where(fn ($q) => $q->whereNull('user_id')->when($user?->id, fn ($w) => $w->orWhere('user_id', $user->id)))
            ->orderBy('name')
            ->get(['id', 'name', 'category', 'user_id']);
    }

    /** Vendors are linked to exactly one supplier catalog profile. */
    private function linkSupplier(User $user, ?int $supplierId): void
    {
        Supplier::where('user_id', $user->id)->update(['user_id' => null]);

        if ($user->role === 'vendor' && $supplierId) {
            Supplier::whereKey($supplierId)->update(['user_id' => $user->id]);
        }
    }
}
