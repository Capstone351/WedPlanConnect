<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Task;
use App\Models\User;
use App\Notifications\PlannerReminder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Lets the Admin remind a Wedding Planner about their overdue tasks and past weddings not yet closed.
 */
class PlannerReminderController extends Controller
{
    public function __invoke(Request $request, User $user): RedirectResponse
    {
        abort_unless($user->role === 'planner', 404);

        $items = self::itemsFor($user);

        if (! $items) {
            return back()->with('status', "{$user->name} has nothing to follow up on.");
        }

        $user->notify(new PlannerReminder($items, $request->user()->name));

        return back()->with('status', "Reminder sent to {$user->name}.");
    }

    /**
     * What a planner still needs to do, worded for the reminder.
     *
     * @return array<int, string>
     */
    public static function itemsFor(User $planner): array
    {
        $tasks = Task::overdue()->where('assigned_to', $planner->id)->with('booking')->orderBy('due_date')->get()
            ->map(fn (Task $t) => "Overdue task: {$t->title}".($t->booking ? " ({$t->booking->client_name})" : '').' — was due '.$t->due_date->format('M j'));

        $weddings = Booking::awaitingClosure()->where('planner_id', $planner->id)->orderBy('event_date')->get()
            ->map(fn (Booking $b) => "Mark {$b->client_name}'s wedding on {$b->event_date->format('M j')} as Completed or Cancelled");

        return $weddings->concat($tasks)->values()->all();
    }
}
