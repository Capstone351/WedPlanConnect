<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Task;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * UC-05 Create and Assign Tasks.
 */
class TaskController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $base = Task::visibleTo($user);

        $tasks = (clone $base)
            ->with('booking', 'assignee')
            ->when($request->filled('booking_id'), fn ($q) => $q->where('booking_id', $request->integer('booking_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('priority'), fn ($q) => $q->where('priority', $request->string('priority')))
            ->when($request->filled('assigned_to'), fn ($q) => $q->where('assigned_to', $request->integer('assigned_to')))
            ->when($request->boolean('overdue'), fn ($q) => $q->overdue())
            ->orderByRaw("CASE status WHEN 'ongoing' THEN 0 WHEN 'pending' THEN 1 ELSE 2 END")
            ->orderBy('due_date')
            ->paginate(20)->withQueryString();

        $stats = [
            'pending' => (clone $base)->where('status', 'pending')->count(),
            'ongoing' => (clone $base)->where('status', 'ongoing')->count(),
            'completed' => (clone $base)->where('status', 'completed')->count(),
            'overdue' => (clone $base)->overdue()->count(),
        ];

        return view('tasks.index', [
            'tasks' => $tasks,
            'stats' => $stats,
            'bookings' => Booking::visibleTo($user)->active()->orderBy('event_date')->get(['id', 'client_name', 'event_date']),
            'assignees' => $this->assignees(),
        ]);
    }

    public function create(Request $request): View
    {
        return view('tasks.form', [
            'task' => new Task([
                'booking_id' => $request->integer('booking_id') ?: null,
                'assigned_to' => $request->user()->role === 'planner' ? $request->user()->id : null,
                'priority' => 'medium',
                'status' => 'pending',
            ]),
            'bookings' => Booking::visibleTo($request->user())->active()->orderBy('event_date')->get(['id', 'client_name', 'event_date']),
            'assignees' => $this->assignees(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $task = Task::create($this->validated($request));

        return $this->redirectAfterSave($request, $task, 'Task created.');
    }

    public function edit(Request $request, Task $task): View
    {
        $this->authorizeTask($task);

        return view('tasks.form', [
            'task' => $task,
            'bookings' => Booking::visibleTo($request->user())->orderBy('event_date')->get(['id', 'client_name', 'event_date']),
            'assignees' => $this->assignees(),
        ]);
    }

    public function update(Request $request, Task $task): RedirectResponse
    {
        $this->authorizeTask($task);
        $task->update($this->validated($request, $task));

        return $this->redirectAfterSave($request, $task, 'Task updated.');
    }

    /** Quick status change from lists (Pending → Ongoing → Completed). */
    public function status(Request $request, Task $task): RedirectResponse
    {
        $this->authorizeTask($task);
        $data = $request->validate(['status' => ['required', Rule::in(Task::STATUSES)]]);
        $task->update($data);

        return back()->with('status', "“{$task->title}” marked as ".ucfirst($data['status']).'.');
    }

    /** Archive (soft delete). */
    public function destroy(Task $task): RedirectResponse
    {
        $this->authorizeTask($task);
        $task->delete();

        return back()->with('status', 'Task archived.');
    }

    private function validated(Request $request, ?Task $task = null): array
    {
        $data = $request->validate([
            'booking_id' => ['nullable', Rule::exists('bookings', 'id')->whereNull('deleted_at')],
            'assigned_to' => ['required', Rule::exists('users', 'id')->whereIn('role', ['planner', 'admin'])->whereNull('deleted_at')],
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:5000'],
            'due_date' => array_filter(['required', 'date', $task ? null : 'after_or_equal:today']),
            'priority' => ['required', Rule::in(Task::PRIORITIES)],
            'status' => ['required', Rule::in(Task::STATUSES)],
        ], ['due_date.after_or_equal' => 'The due date cannot be in the past.']);

        if (! empty($data['booking_id'])) {
            $this->authorizeBooking(Booking::findOrFail($data['booking_id']));
        }

        return $data;
    }

    private function authorizeTask(Task $task): void
    {
        abort_unless(Task::visibleTo(auth()->user())->whereKey($task->id)->exists(), 403);
    }

    private function assignees()
    {
        return User::whereIn('role', ['planner', 'admin'])->orderBy('name')->get(['id', 'name', 'role']);
    }

    private function redirectAfterSave(Request $request, Task $task, string $message): RedirectResponse
    {
        return $request->input('return') === 'booking' && $task->booking_id
            ? redirect()->route('bookings.show', $task->booking_id)->with('status', $message)
            : redirect()->route('tasks.index')->with('status', $message);
    }
}
