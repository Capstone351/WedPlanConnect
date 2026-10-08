@php $editing = $task->exists; @endphp
<x-layouts.app :title="$editing ? 'Edit Task' : 'New Task'" :heading="$editing ? 'Edit Task' : 'Create Task'">
    <form method="POST" action="{{ $editing ? route('tasks.update', $task) : route('tasks.store') }}" class="card max-w-3xl">
        @csrf
        @if ($editing) @method('PUT') @endif
        @if (request('booking_id') || $task->booking_id)
            <input type="hidden" name="return" value="booking">
        @endif
        <div class="card-body grid gap-5 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <label for="title" class="label">Task title</label>
                <input id="title" name="title" value="{{ old('title', $task->title) }}" required maxlength="200" class="input" placeholder="e.g. Finalize floral arch design">
            </div>
            <div class="sm:col-span-2">
                <label for="description" class="label">Description <span class="font-normal text-stone-400">(optional)</span></label>
                <textarea id="description" name="description" rows="3" maxlength="5000" class="input">{{ old('description', $task->description) }}</textarea>
            </div>
            <div>
                <label for="booking_id" class="label">Wedding project</label>
                <select id="booking_id" name="booking_id" class="input">
                    <option value="">General operations (no booking)</option>
                    @foreach ($bookings as $b)
                        <option value="{{ $b->id }}" @selected(old('booking_id', $task->booking_id) == $b->id)>{{ $b->client_name }} — {{ $b->event_date->format('M j, Y') }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="assigned_to" class="label">Assign to</label>
                <select id="assigned_to" name="assigned_to" required class="input">
                    @foreach ($assignees as $a)
                        <option value="{{ $a->id }}" @selected(old('assigned_to', $task->assigned_to) == $a->id)>{{ $a->name }} ({{ $a->roleLabel() }})</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="due_date" class="label">Due date</label>
                <input id="due_date" name="due_date" type="date" value="{{ old('due_date', $task->due_date?->toDateString()) }}" required class="input">
            </div>
            <div>
                <label for="priority" class="label">Priority</label>
                <select id="priority" name="priority" class="input">
                    @foreach (\App\Models\Task::PRIORITIES as $p)<option value="{{ $p }}" @selected(old('priority', $task->priority) === $p)>{{ ucfirst($p) }}</option>@endforeach
                </select>
            </div>
            <div>
                <label for="status" class="label">Status</label>
                <select id="status" name="status" class="input">
                    @foreach (\App\Models\Task::STATUSES as $s)<option value="{{ $s }}" @selected(old('status', $task->status) === $s)>{{ ucfirst($s) }}</option>@endforeach
                </select>
            </div>
        </div>
        <div class="flex justify-end gap-2 border-t border-stone-100 px-5 py-4">
            <a href="{{ url()->previous() }}" class="btn-secondary">Cancel</a>
            <button class="btn-primary">Save task</button>
        </div>
    </form>
</x-layouts.app>
