@php
    $editing = $user->exists;
    $role = old('role', $user->role ?? 'planner');
    $supplierMode = old('supplier_mode', $suppliers->isEmpty() && ! $user->supplierProfile ? 'new' : 'existing');
    $headings = ['planner' => 'Add Wedding Planner', 'vendor' => 'Add Supplier Account'];
@endphp
<x-layouts.app :title="$editing ? 'Edit User' : 'Add User'" :heading="$editing ? 'Edit '.$user->name : ($headings[$role] ?? 'Add New User')" subheading="Accounts are governed by role-based access control.">
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
                <p class="hint">Used to log in.</p>
            </div>
            <div>
                <label for="phone" class="label">Phone <span class="font-normal text-stone-400">(optional)</span></label>
                <input id="phone" name="phone" value="{{ old('phone', $user->phone) }}" maxlength="20" class="input">
            </div>
            <div>
                <label for="role" class="label">Role</label>
                <select id="role" name="role" required class="input" data-role-select>
                    @foreach (\App\Models\User::ROLES as $key => $label)
                        <option value="{{ $key }}" @selected($role === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Wedding Planner --}}
            <div class="sm:col-span-2" data-role-only="planner" @if ($role !== 'planner') hidden @endif>
                <label for="bio" class="label">Short introduction <span class="font-normal text-stone-400">(optional)</span></label>
                <textarea id="bio" name="bio" rows="2" maxlength="500" class="input" placeholder="Shown to couples when they choose their planner, e.g. 10 years styling garden and ballroom weddings in Cebu.">{{ old('bio', $user->bio) }}</textarea>
            </div>

            {{-- Supplier / Vendor: every supplier account belongs to one supplier business in the catalog. --}}
            <fieldset class="space-y-4 rounded-xl border border-line bg-stone-50/60 p-4 sm:col-span-2" data-role-only="vendor" @if ($role !== 'vendor') hidden @endif>
                <legend class="px-1 text-sm font-semibold text-ink">Supplier business</legend>
                <p class="text-xs text-stone-500">The supplier logs in to manage this business's products and inventory, and to answer orders.</p>
                <div class="flex flex-wrap gap-4 text-sm">
                    <label class="flex items-center gap-2"><input type="radio" name="supplier_mode" value="new" data-supplier-mode @checked($supplierMode === 'new')> Create a new supplier business</label>
                    <label class="flex items-center gap-2"><input type="radio" name="supplier_mode" value="existing" data-supplier-mode @checked($supplierMode === 'existing')> Link one already in the catalog</label>
                </div>

                <div class="grid gap-4 sm:grid-cols-2" data-supplier-only="new" @if ($supplierMode !== 'new') hidden @endif>
                    <div class="sm:col-span-2">
                        <label for="new_supplier_name" class="label">Business name</label>
                        <input id="new_supplier_name" name="new_supplier_name" value="{{ old('new_supplier_name') }}" maxlength="150" class="input" placeholder="e.g. Lorr Aljas Event Styling">
                        @error('new_supplier_name')<p class="error-text">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="new_supplier_category" class="label">Category</label>
                        <select id="new_supplier_category" name="new_supplier_category" class="input">
                            <option value="">Choose…</option>
                            @foreach (\App\Models\Supplier::CATEGORIES as $category)
                                <option value="{{ $category }}" @selected(old('new_supplier_category') === $category)>{{ $category }}</option>
                            @endforeach
                        </select>
                        @error('new_supplier_category')<p class="error-text">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="new_supplier_phone" class="label">Business phone</label>
                        <input id="new_supplier_phone" name="new_supplier_phone" value="{{ old('new_supplier_phone') }}" maxlength="20" class="input" placeholder="+639171234567">
                        @error('new_supplier_phone')<p class="error-text">{{ $message }}</p>@enderror
                    </div>
                    <p class="hint sm:col-span-2">The supplier can add a description, prices and products after logging in.</p>
                </div>

                <div data-supplier-only="existing" @if ($supplierMode !== 'existing') hidden @endif>
                    <label for="supplier_id" class="label">Supplier business</label>
                    <select id="supplier_id" name="supplier_id" class="input">
                        <option value="">— None —</option>
                        @foreach ($suppliers as $s)
                            <option value="{{ $s->id }}" @selected(old('supplier_id', $user->supplierProfile?->id) == $s->id)>{{ $s->name }} ({{ $s->category }})</option>
                        @endforeach
                    </select>
                    <p class="hint">{{ $suppliers->isEmpty() ? 'No supplier businesses without a login yet. Create a new one instead.' : 'Only businesses without a login are listed.' }}</p>
                </div>
            </fieldset>

            @if ($editing)
                <div>
                    <label for="password" class="label">New password</label>
                    <input id="password" name="password" type="password" autocomplete="new-password" class="input">
                    <p class="hint">Leave blank to keep the current password.</p>
                </div>
                <div>
                    <label for="password_confirmation" class="label">Confirm password</label>
                    <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" class="input">
                </div>
            @else
                <div class="flex items-start gap-3 rounded-xl border border-gold-300 bg-gold-50 px-4 py-3 text-sm text-stone-700 sm:col-span-2">
                    <x-icon name="lock" class="mt-0.5 size-5 shrink-0 text-gold-700" />
                    <span>No password needed. We'll email this person a link to <strong>set their own password</strong> (valid for {{ \App\Notifications\AccountInvitation::VALID_DAYS }} days), then they can log in.</span>
                </div>
            @endif
        </div>
        <div class="flex justify-end gap-2 border-t border-stone-100 px-5 py-4">
            <a href="{{ route('admin.users.index') }}" class="btn-secondary">Cancel</a>
            <button class="btn-primary">{{ $editing ? 'Save changes' : 'Create account and send email' }}</button>
        </div>
    </form>

    @if ($editing && ! $user->trashed())
        <form method="POST" action="{{ route('admin.users.invite', $user) }}" class="card mt-4 flex max-w-3xl flex-wrap items-center justify-between gap-3 px-5 py-4">
            @csrf
            <div class="text-sm">
                <div class="font-medium">Password set-up link</div>
                <div class="text-stone-500">Emails {{ $user->email }} a new link to choose their password, e.g. if the first one expired.</div>
            </div>
            <button class="btn-secondary btn-sm"><x-icon name="bell" class="size-4" /> Send password set-up link</button>
        </form>
    @endif
</x-layouts.app>
