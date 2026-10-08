<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingTest extends TestCase
{
    use RefreshDatabase;

    private User $planner;

    private User $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->planner = User::factory()->planner()->create();
        $this->client = User::factory()->create();
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'client_id' => $this->client->id,
            'client_name' => 'Ana & Miguel',
            'contact_number' => '+639171234567',
            'event_date' => now()->addMonths(3)->toDateString(),
            'venue' => 'Casa Gorordo Museum',
            'package' => 'Garden Romance',
            'total_amount' => 150000,
        ], $overrides);
    }

    public function test_planner_creates_a_pending_booking(): void
    {
        $this->actingAs($this->planner)->post('/bookings', $this->payload())->assertRedirect();

        $this->assertDatabaseHas('bookings', ['planner_id' => $this->planner->id, 'status' => 'pending', 'venue' => 'Casa Gorordo Museum']);
    }

    public function test_double_booking_same_date_and_venue_is_rejected(): void
    {
        $this->actingAs($this->planner)->post('/bookings', $this->payload());

        $this->actingAs($this->planner)
            ->post('/bookings', $this->payload(['venue' => '  casa gorordo museum ']))
            ->assertSessionHasErrors('event_date');

        $this->assertSame(1, Booking::count());
    }

    public function test_same_date_at_a_different_venue_is_allowed(): void
    {
        $this->actingAs($this->planner)->post('/bookings', $this->payload());
        $this->actingAs($this->planner)->post('/bookings', $this->payload(['venue' => 'Marco Polo Plaza']))->assertSessionHasNoErrors();

        $this->assertSame(2, Booking::count());
    }

    public function test_cancelled_bookings_free_up_the_slot(): void
    {
        Booking::factory()->create(['event_date' => now()->addMonths(3)->toDateString(), 'venue' => 'Casa Gorordo Museum', 'status' => 'cancelled']);

        $this->actingAs($this->planner)->post('/bookings', $this->payload())->assertSessionHasNoErrors();
    }

    public function test_past_event_dates_are_rejected(): void
    {
        $this->actingAs($this->planner)->post('/bookings', $this->payload(['event_date' => now()->subDay()->toDateString()]))
            ->assertSessionHasErrors('event_date');
    }

    public function test_confirming_generates_a_64_char_hex_qr_token(): void
    {
        $booking = Booking::factory()->create(['planner_id' => $this->planner->id]);

        $this->actingAs($this->planner)->patch("/bookings/{$booking->id}/confirm")->assertRedirect("/bookings/{$booking->id}/qr");

        $booking->refresh();
        $this->assertSame('confirmed', $booking->status);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $booking->qr_token);
    }

    public function test_cancelling_invalidates_the_qr_token(): void
    {
        $booking = Booking::factory()->confirmed()->create(['planner_id' => $this->planner->id]);
        $this->assertNotNull($booking->fresh()->qr_token);

        $this->actingAs($this->planner)->patch("/bookings/{$booking->id}/cancel");

        $booking->refresh();
        $this->assertSame('cancelled', $booking->status);
        $this->assertNull($booking->qr_token);
    }

    public function test_planner_cannot_open_another_planners_booking(): void
    {
        $other = Booking::factory()->create();

        $this->actingAs($this->planner)->get("/bookings/{$other->id}")->assertForbidden();
        $this->actingAs(User::factory()->admin()->create())->get("/bookings/{$other->id}")->assertOk();
    }

    public function test_suppliers_can_be_assigned_and_status_updated(): void
    {
        $booking = Booking::factory()->create(['planner_id' => $this->planner->id]);
        $supplier = Supplier::factory()->create();

        $this->actingAs($this->planner)->post("/bookings/{$booking->id}/suppliers", ['supplier_ids' => [$supplier->id]]);
        $assignment = $booking->bookingSuppliers()->first();
        $this->assertSame('pending', $assignment->status);

        $this->actingAs($this->planner)->patch("/bookings/{$booking->id}/suppliers/{$assignment->id}", ['status' => 'confirmed']);
        $this->assertSame('confirmed', $assignment->fresh()->status);
    }

    public function test_new_client_account_can_be_created_inline(): void
    {
        $this->actingAs($this->planner)->post('/bookings', $this->payload([
            'client_id' => null, 'new_client_name' => 'Bea Lim', 'new_client_email' => 'bea@example.com',
        ]))->assertSessionHasNoErrors();

        $client = User::where('email', 'bea@example.com')->first();
        $this->assertSame('client', $client->role);
        $this->assertDatabaseHas('bookings', ['client_id' => $client->id]);
    }
}
