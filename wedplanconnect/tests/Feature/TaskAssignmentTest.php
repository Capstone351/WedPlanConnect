<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskAssignmentTest extends TestCase
{
    use RefreshDatabase;

    private function taskData(array $overrides = []): array
    {
        return ['title' => 'Food tasting', 'due_date' => today()->addWeek()->toDateString(), 'priority' => 'medium', 'status' => 'pending', ...$overrides];
    }

    public function test_task_form_has_no_assignee_choice(): void
    {
        $planner = User::factory()->planner()->create();

        $this->actingAs($planner)->get('/tasks/create')->assertOk()->assertDontSee('name="assigned_to"', false)->assertSee("go to that wedding's planner automatically", false);
        $this->actingAs($planner)->get('/tasks')->assertOk()->assertDontSee('Assigned to')->assertDontSee('name="assigned_to"', false);
    }

    public function test_a_wedding_task_goes_to_that_weddings_planner_whoever_is_picked(): void
    {
        $booking = Booking::factory()->create();
        $someoneElse = User::factory()->planner()->create();

        $this->actingAs($booking->planner)->post('/tasks', $this->taskData(['booking_id' => $booking->id, 'assigned_to' => $someoneElse->id]))->assertSessionHasNoErrors();

        $this->assertSame($booking->planner_id, Task::where('title', 'Food tasting')->sole()->assigned_to);
    }

    public function test_a_general_task_stays_with_the_person_who_made_it(): void
    {
        $planner = User::factory()->planner()->create();

        $this->actingAs($planner)->post('/tasks', $this->taskData())->assertSessionHasNoErrors();
        $task = Task::sole();
        $this->assertSame($planner->id, $task->assigned_to);
    }

    public function test_changing_a_weddings_planner_moves_its_open_tasks(): void
    {
        $booking = Booking::factory()->create();
        $open = Task::create(['booking_id' => $booking->id, 'assigned_to' => $booking->planner_id, 'title' => 'Call florist', 'due_date' => today()->addWeek()]);
        $done = Task::create(['booking_id' => $booking->id, 'assigned_to' => $booking->planner_id, 'title' => 'Sign contract', 'due_date' => today(), 'status' => 'completed']);
        $oldPlanner = $booking->planner_id;
        $newPlanner = User::factory()->planner()->create();

        $booking->update(['planner_id' => $newPlanner->id]);

        $this->assertSame($newPlanner->id, $open->fresh()->assigned_to);
        $this->assertSame($oldPlanner, $done->fresh()->assigned_to);
    }
}
