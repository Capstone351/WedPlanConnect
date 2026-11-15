<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Meeting;
use App\Models\User;
use App\Notifications\MeetingAnswered;
use App\Notifications\MeetingCancelled;
use App\Notifications\MeetingRequested;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MeetingTest extends TestCase
{
    use RefreshDatabase;

    private User $planner;

    private User $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->planner = User::factory()->planner()->create(['name' => 'Maria Clara Uy']);
        $this->client = User::factory()->create(['bride_name' => 'Ana Santos', 'groom_name' => 'Miguel Reyes']);
        $this->client->forceFill(['preferred_planner_id' => $this->planner->id])->save();
    }

    /**
     * @return array{date: string, time: string, mode: string, location: string, agenda: string}
     */
    private function request(int $daysAhead = 3, string $time = '14:30'): array
    {
        return ['date' => today()->addDays($daysAhead)->toDateString(), 'time' => $time, 'mode' => 'in_person', 'location' => 'FMT Office', 'agenda' => 'Theme ideas'];
    }

    public function test_couple_requests_a_meeting_and_the_planner_confirms_it(): void
    {
        $this->actingAs($this->client)->get('/my/meetings')->assertOk()->assertSee('With')->assertSee('Maria Clara Uy');

        $this->actingAs($this->client)->post('/my/meetings', $this->request())->assertSessionHasNoErrors()->assertRedirect('/my/meetings');

        $meeting = Meeting::sole();
        $this->assertSame([$this->client->id, $this->planner->id, 'requested'], [$meeting->client_id, $meeting->planner_id, $meeting->status]);
        $this->assertSame(today()->addDays(3)->setTime(14, 30)->toDateTimeString(), $meeting->scheduled_at->toDateTimeString());
        $this->assertSame(MeetingRequested::class, $this->planner->notifications()->sole()->type);

        $this->actingAs($this->planner)->get('/meetings')->assertOk()->assertSee('Ana Santos &amp; Miguel Reyes', false)->assertSee('Theme ideas');

        $this->actingAs($this->planner)->patch("/meetings/{$meeting->id}", ['status' => 'confirmed', 'response_note' => 'See you there!'])
            ->assertSessionHasNoErrors();
        $this->assertSame('confirmed', $meeting->fresh()->status);
        $this->assertSame(MeetingAnswered::class, $this->client->notifications()->sole()->type);

        $this->actingAs($this->client)->get('/my/meetings')->assertOk()->assertSee('Confirmed')->assertSee('See you there!');
    }

    public function test_couple_cancels_a_meeting_and_the_planner_is_told(): void
    {
        $meeting = Meeting::factory()->confirmed()->create(['client_id' => $this->client->id, 'planner_id' => $this->planner->id]);

        $this->actingAs(User::factory()->create())->patch("/my/meetings/{$meeting->id}/cancel")->assertForbidden();

        $this->actingAs($this->client)->patch("/my/meetings/{$meeting->id}/cancel")->assertSessionHasNoErrors();
        $this->assertSame('cancelled', $meeting->fresh()->status);
        $this->assertSame(MeetingCancelled::class, $this->planner->notifications()->sole()->type);
    }

    public function test_meeting_goes_to_the_booked_planner_once_the_couple_has_a_booking(): void
    {
        $booked = User::factory()->planner()->create();
        Booking::factory()->create(['client_id' => $this->client->id, 'planner_id' => $booked->id, 'event_date' => today()->addMonths(4)]);

        $this->actingAs($this->client)->post('/my/meetings', $this->request())->assertSessionHasNoErrors();

        $this->assertSame($booked->id, Meeting::sole()->planner_id);
    }

    public function test_meeting_must_fit_the_planners_schedule(): void
    {
        Meeting::factory()->create(['planner_id' => $this->planner->id, 'scheduled_at' => today()->addDays(3)->setTime(14, 0)]);
        Booking::factory()->create(['planner_id' => $this->planner->id, 'event_date' => today()->addDays(5)]);

        // Within an hour of another meeting, on the planner's wedding day, or in the past.
        $this->actingAs($this->client)->post('/my/meetings', $this->request(3, '14:30'))->assertSessionHasErrors('time');
        $this->actingAs($this->client)->post('/my/meetings', $this->request(5, '10:00'))->assertSessionHasErrors('time');
        $this->actingAs($this->client)->post('/my/meetings', $this->request(-1))->assertSessionHasErrors('date');

        $this->actingAs($this->client)->post('/my/meetings', $this->request(3, '15:00'))->assertSessionHasNoErrors();

        $this->client->forceFill(['preferred_planner_id' => null])->save();
        $this->actingAs($this->client)->post('/my/meetings', $this->request(4))->assertSessionHasErrors('meeting');

        $this->assertDatabaseCount('meetings', 2);
    }

    public function test_a_couple_cannot_be_in_two_meetings_at_once(): void
    {
        $this->actingAs($this->client)->post('/my/meetings', $this->request(3, '14:30'))->assertSessionHasNoErrors();

        // Their own request holds the slot, even if their planner changes.
        $this->client->forceFill(['preferred_planner_id' => User::factory()->planner()->create()->id])->save();
        $this->actingAs($this->client)->post('/my/meetings', $this->request(3, '15:00'))
            ->assertSessionHasErrors(['time' => 'You already asked for a meeting on '.Meeting::sole()->when().'. Please pick another time, or cancel that one first.']);

        Meeting::sole()->update(['status' => 'cancelled']);
        $this->actingAs($this->client)->post('/my/meetings', $this->request(3, '15:00'))->assertSessionHasNoErrors();
    }

    public function test_planner_cannot_confirm_a_meeting_that_now_clashes(): void
    {
        $meeting = Meeting::factory()->create(['client_id' => $this->client->id, 'planner_id' => $this->planner->id, 'scheduled_at' => today()->addDays(6)->setTime(10, 0)]);

        // A wedding was booked on that day after the couple asked.
        $wedding = Booking::factory()->create(['planner_id' => $this->planner->id, 'event_date' => today()->addDays(6), 'status' => 'confirmed']);
        $this->actingAs($this->planner)->patch("/meetings/{$meeting->id}", ['status' => 'confirmed'])->assertSessionHasErrors('meeting');
        $this->assertSame('requested', $meeting->fresh()->status);

        // Another meeting already confirmed in the same hour.
        $wedding->update(['event_date' => today()->addDays(20)]);
        Meeting::factory()->confirmed()->create(['planner_id' => $this->planner->id, 'scheduled_at' => today()->addDays(6)->setTime(10, 30)]);
        $this->actingAs($this->planner)->patch("/meetings/{$meeting->id}", ['status' => 'confirmed'])->assertSessionHasErrors('meeting');

        // Declining always works.
        $this->actingAs($this->planner)->patch("/meetings/{$meeting->id}", ['status' => 'declined'])->assertSessionHasNoErrors();
        $this->assertSame('declined', $meeting->fresh()->status);
    }

    public function test_planners_only_answer_their_own_meetings(): void
    {
        $meeting = Meeting::factory()->create(['client_id' => $this->client->id, 'planner_id' => $this->planner->id]);

        $this->actingAs(User::factory()->planner()->create())->patch("/meetings/{$meeting->id}", ['status' => 'confirmed'])->assertForbidden();
        $this->actingAs($this->client)->get('/meetings')->assertForbidden();
        $this->assertSame('requested', $meeting->fresh()->status);
    }
}
