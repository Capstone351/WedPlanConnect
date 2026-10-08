<x-layouts.app title="User Management" heading="User Management" subheading="Create, edit, deactivate, and audit accounts for every role.">
    <x-slot:actions>
        <a href="{{ route('admin.users.create') }}" class="btn-primary"><x-icon name="plus" class="size-4" /> Add New User</a>
    </x-slot:actions>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach (\App\Models\User::ROLES as $key => $label)
            <x-stat :label="$label" :value="$counts[$key] ?? 0" :icon="['admin' => 'lock', 'planner' => 'clipboard', 'client' => 'heart', 'vendor' => 'store'][$key]" />
        @endforeach
    </div>

    <div class="card mt-6">
        <form class="flex flex-wrap items-end gap-3 border-b border-stone-100 p-4">
            <div class="min-w-48 flex-1">
                <label class="label" for="q">Search</label>
                <input id="q" name="q" value="{{ request('q') }}" placeholder="Name or email" class="input">
            </div>
            <div>
                <label class="label" for="role">Role</label>
                <select id="role" name="role" class="input" data-autosubmit>
                    <option value="">All roles</option>
                    @foreach (\App\Models\User::ROLES as $key => $label)
                        <option value="{{ $key }}" @selected(request('role') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="label" for="status">Status</label>
                <select id="status" name="status" class="input" data-autosubmit>
                    <option value="">Any status</option>
                    @foreach (['active' => 'Active', 'locked' => 'Locked', 'inactive' => 'Inactive'] as $key => $label)
                        <option value="{{ $key }}" @selected(request('status') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <button class="btn-secondary">Filter</button>
        </form>
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Last login</th><th class="text-right">Actions</th></tr></thead>
                <tbody>
                    @forelse ($users as $u)
                        @php $status = $u->accountStatus(); @endphp
                        <tr @class(['opacity-60' => $u->trashed()])>
                            <td class="font-medium">{{ $u->name }}</td>
                            <td>{{ $u->email }}</td>
                            <td><span class="badge-brand">{{ $u->roleLabel() }}</span></td>
                            <td><span class="badge-{{ ['Active' => 'green', 'Locked' => 'amber', 'Inactive' => 'gray'][$status] }}">{{ $status }}</span></td>
                            <td class="text-stone-500">{{ $u->last_login_at?->diffForHumans() ?? 'Never' }}</td>
                            <td>
                                <div class="flex justify-end gap-2">
                                    <a href="{{ route('admin.users.edit', $u) }}" class="btn-secondary btn-sm">Edit</a>
                                    @if ($status === 'Locked')
                                        <form method="POST" action="{{ route('admin.users.unlock', $u) }}">@csrf @method('PATCH')<button class="btn-secondary btn-sm">Unlock</button></form>
                                    @endif
                                    @if ($u->trashed())
                                        <form method="POST" action="{{ route('admin.users.restore', $u) }}">@csrf @method('PATCH')<button class="btn-success btn-sm">Reactivate</button></form>
                                    @elseif (! $u->is(auth()->user()))
                                        <form method="POST" action="{{ route('admin.users.destroy', $u) }}" data-confirm="Deactivate {{ $u->name }}? They will no longer be able to log in.">@csrf @method('DELETE')<button class="btn-danger btn-sm">Deactivate</button></form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="py-8 text-center text-stone-500">No users match your filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4">{{ $users->links() }}</div>
    </div>
</x-layouts.app>
