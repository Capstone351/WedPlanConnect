<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PackageTasksTest extends TestCase
{
    use RefreshDatabase;

    public function test_sending_a_booking_turns_the_package_inclusions_into_tasks(): void
    {
        $planner = User::factory()->planner()->create();
        $client = User::factory()->create(['phone' => '09171234567']);
        $client->forceFill(['preferred_planner_id' => $planner->id, 'wedding_date' => today()->addYear(), 'wedding_package' => 'Package A'])->save();

        $this->actingAs($client)->post('/my/booking-request')->assertSessionHasNoErrors();

        $booking = Booking::sole();
        $this->assertSame(count(Booking::packageTasks('Package A')), $booking->tasks()->count());

        $cake = $booking->tasks()->where('title', 'Cake')->sole();
        $this->assertSame($planner->id, $cake->assigned_to);
        $this->assertSame(today()->addYear()->subDays(45)->toDateString(), $cake->due_date->toDateString());
        $this->assertTrue($booking->tasks()->where('title', '4 On-the-Day Coordinators')->exists());
        $this->assertTrue($booking->tasks()->where('title', 'Freebie: Bridesmaids Robe for Dress-up/Makeup')->exists());

        $this->actingAs($planner)->get('/tasks')->assertOk()->assertSee('Church Decoration');
        $this->actingAs($client)->get('/my')->assertOk()->assertSee('Photo/Video (w/ Pre-nup)');
    }

    public function test_changing_the_package_updates_untouched_tasks_and_keeps_worked_on_ones(): void
    {
        $booking = Booking::factory()->create(['package' => 'Package A', 'event_date' => today()->addYear()]);
        $booking->tasks()->where('title', 'Cake')->update(['status' => 'completed']);
        $own = Task::create(['booking_id' => $booking->id, 'assigned_to' => $booking->planner_id, 'title' => 'Food tasting', 'due_date' => today()->addMonth()]);

        $booking->update(['package' => 'Package C']);

        $titles = $booking->tasks()->pluck('title');
        $this->assertContains('2 On-the-Day Coordinators', $titles);
        $this->assertNotContains('4 On-the-Day Coordinators', $titles);
        $this->assertNotContains('Freebie: Bridesmaids Robe for Dress-up/Makeup', $titles);
        $this->assertContains('Food tasting', $titles);
        $this->assertSame(1, $booking->tasks()->where('title', 'Cake')->count());
        $this->assertSame('completed', $booking->tasks()->where('title', 'Cake')->sole()->status);
        $this->assertModelExists($own);

        // A new wedding date moves the untouched due dates with it.
        $booking->update(['event_date' => today()->addYear()->addMonth()]);
        $this->assertSame(today()->addYear()->addMonth()->subDays(180)->toDateString(), $booking->tasks()->where('title', 'Reception Area')->sole()->due_date->toDateString());
    }

    public function test_a_close_wedding_date_never_gets_due_dates_in_the_past(): void
    {
        $booking = Booking::factory()->create(['package' => 'Package B', 'event_date' => today()->addWeek()]);

        $this->assertSame(0, $booking->tasks()->whereDate('due_date', '<', today())->count());
        $this->assertSame(0, $booking->tasks()->whereDate('due_date', '>', today()->addWeek())->count());
    }

    public function test_custom_packages_get_no_package_tasks(): void
    {
        $booking = Booking::factory()->create(['package' => 'Custom Package']);

        $this->assertSame(0, $booking->tasks()->count());
    }
}
