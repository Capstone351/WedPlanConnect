<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Task;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * UC-05 Create and Assign Tasks. Tasks are assigned automatically to the wedding's planner.
 */
class TaskController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $base = Task::visibleTo($user);

        $tasks = (clone $base)
            ->with('booking')
            ->when($request->filled('booking_id'), fn ($q) => $q->where('booking_id', $request->integer('booking_id')))
            // A to-do list shows what is left by default; "all" brings back finished tasks.
            ->when(in_array($request->input('status'), Task::STATUSES, true), fn ($q) => $q->where('status', $request->input('status')))
            ->when(! $request->filled('status'), fn ($q) => $q->open())
            ->when($request->filled('priority'), fn ($q) => $q->where('priority', $request->string('priority')))
            ->when($request->boolean('overdue'), fn ($q) => $q->overdue())
            // Soonest due first (so overdue work is on top), finished tasks last, importance breaks ties.
            ->orderByRaw("CASE WHEN status = 'completed' THEN 1 ELSE 0 END")
            ->orderBy('due_date')
            ->orderByRaw("CASE priority WHEN 'high' THEN 0 WHEN 'medium' THEN 1 ELSE 2 END")
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
            'bookings' => Booking::visibleTo($user)->where('status', '!=', 'cancelled')->orderByDesc('event_date')->get(['id', 'client_name', 'event_date', 'status']),
        ]);
    }

    public function create(Request $request): View
    {
        return view('tasks.form', [
            'task' => new Task([
                'booking_id' => $request->integer('booking_id') ?: null,
                'priority' => 'medium',
                'status' => 'pending',
            ]),
            'bookings' => Booking::visibleTo($request->user())->active()->orderBy('event_date')->get(['id', 'client_name', 'event_date']),
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
        $booking = $request->filled('booking_id') ? Booking::find($request->integer('booking_id')) : null;
        $dueDateChanged = ! $task || $request->input('due_date') !== $task->due_date?->toDateString();
        // A wedding task has to be done by the wedding; past weddings keep their old dates when edited.
        $dueByWedding = $booking?->event_date && $booking->event_date->gte(today()) ? 'before_or_equal:'.$booking->event_date->toDateString() : null;

        $data = $request->validate([
            'booking_id' => ['nullable', Rule::exists('bookings', 'id')->whereNull('deleted_at')],
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:5000'],
            'due_date' => array_values(array_filter(['required', 'date', $dueDateChanged ? 'after_or_equal:today' : null, $dueByWedding])),
            'priority' => ['required', Rule::in(Task::PRIORITIES)],
            'status' => ['required', Rule::in(Task::STATUSES)],
        ], [
            'due_date.after_or_equal' => 'The due date cannot be in the past.',
            'due_date.before_or_equal' => 'A wedding task must be due on or before the wedding ('.$booking?->event_date?->format('F j, Y').').',
        ]);

        // Nobody picks an assignee: a wedding's tasks go to its planner, general tasks to whoever made them.
        if ($booking) {
            $this->authorizeBooking($booking);
            $data['assigned_to'] = $booking->planner_id;
        } else {
            // Editing a general task keeps its owner; a new one (or one moved off a wedding) goes to the editor.
            $data['assigned_to'] = $task && $task->booking_id === null ? $task->assigned_to : $request->user()->id;
        }

        return $data;
    }

    private function authorizeTask(Task $task): void
    {
        abort_unless(Task::visibleTo(auth()->user())->whereKey($task->id)->exists(), 403);
    }

    private function redirectAfterSave(Request $request, Task $task, string $message): RedirectResponse
    {
        return $request->input('return') === 'booking' && $task->booking_id
            ? redirect()->route('bookings.show', $task->booking_id)->with('status', $message)
            : redirect()->route('tasks.index')->with('status', $message);
    }
}
