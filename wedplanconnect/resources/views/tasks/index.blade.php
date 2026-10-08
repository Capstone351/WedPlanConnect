<x-layouts.app title="Tasks" heading="To-Do Tasks" subheading="Wedding preparation tasks. Overdue tasks are flagged automatically every day.">
    <x-slot:actions>
        <a href="{{ route('tasks.create') }}" class="btn-primary"><x-icon name="plus" class="size-4" /> Add New Task</a>
    </x-slot:actions>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat label="Pending" :value="$stats['pending']" icon="bell" tone="amber" />
        <x-stat label="Ongoing" :value="$stats['ongoing']" icon="clipboard" tone="blue" />
        <x-stat label="Completed" :value="$stats['completed']" icon="check" tone="green" />
        <x-stat label="Overdue" :value="$stats['overdue']" icon="warning" :tone="$stats['overdue'] ? 'red' : 'green'" />
    </div>

    <div class="card mt-6">
        <form class="flex flex-wrap items-end gap-3 border-b border-stone-100 p-4">
            <div class="min-w-48 flex-1">
                <label class="label" for="booking_id">Wedding project</label>
                <select id="booking_id" name="booking_id" class="input" data-autosubmit>
                    <option value="">All projects</option>
                    @foreach ($bookings as $b)
                        <option value="{{ $b->id }}" @selected(request('booking_id') == $b->id)>{{ $b->client_name }} ({{ $b->event_date->format('M j') }})</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="label" for="status">Status</label>
                <select id="status" name="status" class="input" data-autosubmit>
                    <option value="">Any</option>
                    @foreach (\App\Models\Task::STATUSES as $s)<option value="{{ $s }}" @selected(request('status') === $s)>{{ ucfirst($s) }}</option>@endforeach
                </select>
            </div>
            <div>
                <label class="label" for="priority">Priority</label>
                <select id="priority" name="priority" class="input" data-autosubmit>
                    <option value="">Any</option>
                    @foreach (\App\Models\Task::PRIORITIES as $p)<option value="{{ $p }}" @selected(request('priority') === $p)>{{ ucfirst($p) }}</option>@endforeach
                </select>
            </div>
            <div>
                <label class="label" for="assigned_to">Assignee</label>
                <select id="assigned_to" name="assigned_to" class="input" data-autosubmit>
                    <option value="">Anyone</option>
                    @foreach ($assignees as $a)<option value="{{ $a->id }}" @selected(request('assigned_to') == $a->id)>{{ $a->name }}</option>@endforeach
                </select>
            </div>
            <label class="flex items-center gap-2 pb-2 text-sm"><input type="checkbox" name="overdue" value="1" data-autosubmit @checked(request('overdue'))> Overdue only</label>
        </form>
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Task</th><th>Project</th><th>Due</th><th>Priority</th><th>Assigned to</th><th>Status</th><th class="text-right">Actions</th></tr></thead>
                <tbody>
                    @forelse ($tasks as $t)
                        <tr>
                            <td>
                                <div @class(['font-medium', 'text-stone-400 line-through' => $t->status === 'completed'])>{{ $t->title }}</div>
                                @if ($t->description)<div class="max-w-xs truncate text-xs text-stone-500">{{ $t->description }}</div>@endif
                            </td>
                            <td>
                                @if ($t->booking)
                                    <a href="{{ route('bookings.show', $t->booking_id) }}" class="link">{{ $t->booking->client_name }}</a>
                                @else
                                    <span class="text-stone-500">General</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap">
                                {{ $t->due_date->format('M j, Y') }}
                                @if ($t->is_overdue && $t->status !== 'completed')<div><span class="badge-red">Overdue</span></div>@endif
                            </td>
                            <td><span class="badge-{{ \App\Models\Task::priorityBadge($t->priority) }}">{{ ucfirst($t->priority) }}</span></td>
                            <td>{{ $t->assignee?->name }}</td>
                            <td>
                                <form method="POST" action="{{ route('tasks.status', $t) }}">
                                    @csrf @method('PATCH')
                                    <select name="status" class="input py-1 text-xs" data-autosubmit aria-label="Status">
                                        @foreach (\App\Models\Task::STATUSES as $s)<option value="{{ $s }}" @selected($t->status === $s)>{{ ucfirst($s) }}</option>@endforeach
                                    </select>
                                </form>
                            </td>
                            <td>
                                <div class="flex items-center justify-end gap-1">
                                    <a href="{{ route('tasks.edit', $t) }}" class="btn-secondary btn-sm">Edit</a>
                                    <form method="POST" action="{{ route('tasks.destroy', $t) }}" data-confirm="Archive this task?">@csrf @method('DELETE')<button class="btn-icon" title="Archive task" aria-label="Archive {{ $t->title }}"><x-icon name="archive" class="size-4" /></button></form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="py-10 text-center text-stone-500">No tasks match your filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4">{{ $tasks->links() }}</div>
    </div>
</x-layouts.app>
