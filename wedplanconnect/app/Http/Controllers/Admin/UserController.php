<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
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
            ->when($request->input('status') === 'active', fn ($query) => $query->whereNull('deleted_at'))
            ->when($request->input('status') === 'inactive', fn ($query) => $query->whereNotNull('deleted_at'))
            ->when($request->input('status') === 'locked', fn ($query) => $query->where('locked_until', '>', now()))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        $counts = User::selectRaw('role, count(*) as total')->groupBy('role')->pluck('total', 'role');

        return view('admin.users.index', compact('users', 'counts'));
    }

    public function create(): View
    {
        return view('admin.users.form', ['user' => new User, 'suppliers' => $this->linkableSuppliers()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $user = User::create($data);
        $this->linkSupplier($user, $request->integer('supplier_id') ?: null);

        return redirect()->route('admin.users.index')->with('status', "Account for {$user->name} created.");
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
        $this->linkSupplier($user, $request->integer('supplier_id') ?: null);

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
            'password' => [$user ? 'nullable' : 'required', 'confirmed', Password::min(8)->letters()->numbers()],
            'supplier_id' => ['nullable', 'exists:suppliers,id'],
        ]), 'supplier_id');
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
