@php $editing = $user->exists; @endphp
<x-layouts.app :title="$editing ? 'Edit User' : 'Add User'" :heading="$editing ? 'Edit '.$user->name : 'Add New User'" subheading="Accounts are governed by role-based access control.">
    <form method="POST" action="{{ $editing ? route('admin.users.update', $user) : route('admin.users.store') }}" class="card max-w-3xl">
        @csrf
        @if ($editing) @method('PUT') @endif
        <div class="card-body grid gap-5 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <label for="name" class="label">Full name</label>
                <input id="name" name="name" value="{{ old('name', $user->name) }}" required maxlength="150" class="input">
            </div>
            <div>
                <label for="email" class="label">Email</label>
                <input id="email" name="email" type="email" value="{{ old('email', $user->email) }}" required maxlength="150" class="input">
            </div>
            <div>
                <label for="phone" class="label">Phone <span class="font-normal text-stone-400">(optional)</span></label>
                <input id="phone" name="phone" value="{{ old('phone', $user->phone) }}" maxlength="20" class="input">
            </div>
            <div>
                <label for="role" class="label">Role</label>
                <select id="role" name="role" required class="input">
                    @foreach (\App\Models\User::ROLES as $key => $label)
                        <option value="{{ $key }}" @selected(old('role', $user->role ?? 'planner') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="supplier_id" class="label">Linked supplier profile <span class="font-normal text-stone-400">(vendors only)</span></label>
                <select id="supplier_id" name="supplier_id" class="input">
                    <option value="">— None —</option>
                    @foreach ($suppliers as $s)
                        <option value="{{ $s->id }}" @selected(old('supplier_id', $user->supplierProfile?->id) == $s->id)>{{ $s->name }} ({{ $s->category }})</option>
                    @endforeach
                </select>
                <p class="hint">Vendors see only bookings their supplier profile is assigned to.</p>
            </div>
            <div>
                <label for="password" class="label">{{ $editing ? 'New password' : 'Password' }}</label>
                <input id="password" name="password" type="password" @required(! $editing) autocomplete="new-password" class="input">
                <p class="hint">{{ $editing ? 'Leave blank to keep the current password.' : 'At least 8 characters with letters and numbers.' }}</p>
            </div>
            <div>
                <label for="password_confirmation" class="label">Confirm password</label>
                <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" class="input">
            </div>
        </div>
        <div class="flex justify-end gap-2 border-t border-stone-100 px-5 py-4">
            <a href="{{ route('admin.users.index') }}" class="btn-secondary">Cancel</a>
            <button class="btn-primary">{{ $editing ? 'Save changes' : 'Create account' }}</button>
        </div>
    </form>
</x-layouts.app>
