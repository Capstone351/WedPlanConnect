<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingSupplier;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Models\User;
use App\Notifications\SupplierBookingUpdated;
use App\Notifications\SupplierOrderAnswered;
use App\Notifications\SupplierOrderSent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SupplierOrderTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: Booking, 1: User, 2: BookingSupplier}
     */
    private function bookingWithVendorOrder(): array
    {
        $vendor = User::factory()->vendor()->create();
        $supplier = Supplier::factory()->create(['user_id' => $vendor->id]);
        $booking = Booking::factory()->create();
        $product = SupplierProduct::create(['supplier_id' => $supplier->id, 'name' => 'Bridal bouquet', 'price' => 6500, 'is_available' => true]);
        $booking->bookingProducts()->create(['supplier_product_id' => $product->id, 'quantity' => 2]);
        $assignment = $booking->bookingSuppliers()->create(['supplier_id' => $supplier->id, 'status' => 'pending']);

        return [$booking, $vendor, $assignment];
    }

    public function test_planner_sends_order_and_vendor_is_notified(): void
    {
        Notification::fake();
        [$booking, $vendor, $assignment] = $this->bookingWithVendorOrder();

        $this->actingAs($booking->planner)->post("/bookings/{$booking->id}/suppliers/{$assignment->id}/send")
            ->assertSessionHasNoErrors();

        $this->assertTrue($assignment->fresh()->awaitingReply());
        Notification::assertSentTo($vendor, SupplierOrderSent::class, fn ($n, $channels) => $n->items->count() === 1 && $channels === ['database', 'mail']);
    }

    public function test_assigning_a_supplier_notifies_them_right_away(): void
    {
        Notification::fake();
        $vendor = User::factory()->vendor()->create();
        $withLogin = Supplier::factory()->create(['user_id' => $vendor->id, 'name' => 'Lorr Aljas']);
        $noContact = Supplier::factory()->create(['user_id' => null, 'email' => null, 'name' => 'Walk-in Florist']);
        $booking = Booking::factory()->create();

        $this->actingAs($booking->planner)->post("/bookings/{$booking->id}/suppliers", ['supplier_ids' => [$withLogin->id, $noContact->id]])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', fn ($s) => str_contains($s, 'Order sent to Lorr Aljas.') && str_contains($s, 'Walk-in Florist has no vendor account or email'));

        Notification::assertSentTo($vendor, SupplierOrderSent::class, fn ($n, $channels) => $channels === ['database', 'mail']);
        $this->assertTrue($booking->bookingSuppliers()->where('supplier_id', $withLogin->id)->sole()->awaitingReply());
        $this->assertSame('pending', $booking->bookingSuppliers()->where('supplier_id', $noContact->id)->sole()->status);

        // Assigning them again doesn't send a second order.
        $this->actingAs($booking->planner)->post("/bookings/{$booking->id}/suppliers", ['supplier_ids' => [$withLogin->id]]);
        Notification::assertSentToTimes($vendor, SupplierOrderSent::class, 1);
    }

    public function test_suppliers_hear_when_removed_or_the_wedding_is_cancelled_or_moved(): void
    {
        Notification::fake();
        [$booking, $vendor, $assignment] = $this->bookingWithVendorOrder();
        $booking->update(['status' => 'confirmed']);
        $assignment->markOrderSent();

        // Date changed.
        $this->actingAs($booking->planner)->put("/bookings/{$booking->id}", [
            'client_id' => $booking->client_id, 'client_name' => $booking->client_name, 'contact_number' => '09171234567',
            'event_date' => $booking->event_date->copy()->addWeek()->toDateString(), 'venue' => $booking->venue, 'package' => $booking->package,
            'total_amount' => $booking->total_amount, 'change_reason' => 'Venue moved the date.',
        ])->assertSessionHasNoErrors();
        Notification::assertSentTo($vendor, SupplierBookingUpdated::class, fn ($n) => $n->title === 'Wedding details changed' && str_contains($n->message, 'Venue moved the date.'));

        // Cancelled.
        $this->actingAs($booking->planner)->patch("/bookings/{$booking->id}/cancel", ['reason' => 'The couple called it off.'])->assertSessionHasNoErrors();
        Notification::assertSentTo($vendor, SupplierBookingUpdated::class, fn ($n) => $n->title === 'Wedding cancelled' && str_contains($n->message, 'The couple called it off.'));

        // Removed from another wedding after the order was sent.
        [$other, $otherVendor, $otherAssignment] = $this->bookingWithVendorOrder();
        $otherAssignment->markOrderSent();
        $this->actingAs($other->planner)->delete("/bookings/{$other->id}/suppliers/{$otherAssignment->id}")->assertSessionHasNoErrors();
        Notification::assertSentTo($otherVendor, SupplierBookingUpdated::class, fn ($n) => $n->title === 'Removed from a wedding' && ! $n->stillAssigned);
    }

    public function test_supplier_without_login_gets_the_order_by_email_only(): void
    {
        Notification::fake();
        $booking = Booking::factory()->create();
        $supplier = Supplier::factory()->create(['user_id' => null, 'email' => 'cakes@example.com']);
        $assignment = $booking->bookingSuppliers()->create(['supplier_id' => $supplier->id, 'status' => 'pending']);

        $this->actingAs($booking->planner)->post("/bookings/{$booking->id}/suppliers/{$assignment->id}/send")
            ->assertSessionHasNoErrors();

        Notification::assertSentTo(new AnonymousNotifiable, SupplierOrderSent::class,
            fn ($n, $channels, $notifiable) => $notifiable->routes['mail'] === 'cakes@example.com' && $channels === ['mail']);
    }

    public function test_order_cannot_be_sent_to_a_supplier_without_any_contact(): void
    {
        Notification::fake();
        $booking = Booking::factory()->create();
        $supplier = Supplier::factory()->create(['user_id' => null, 'email' => null]);
        $assignment = $booking->bookingSuppliers()->create(['supplier_id' => $supplier->id, 'status' => 'pending']);

        $this->actingAs($booking->planner)->post("/bookings/{$booking->id}/suppliers/{$assignment->id}/send")
            ->assertSessionHasErrors('order');

        $this->assertSame('pending', $assignment->fresh()->status);
        Notification::assertNothingSent();
    }

    public function test_vendor_accepts_order_and_planner_is_notified(): void
    {
        Notification::fake();
        [$booking, $vendor, $assignment] = $this->bookingWithVendorOrder();
        $assignment->markOrderSent();

        $this->actingAs($vendor)->get("/vendor/bookings/{$booking->id}")->assertOk()->assertSee('New order to review');
        $this->actingAs($vendor)->post("/vendor/bookings/{$booking->id}/respond", ['decision' => 'accept'])
            ->assertSessionHasNoErrors();

        $assignment->refresh();
        $this->assertSame('confirmed', $assignment->status);
        $this->assertNotNull($assignment->responded_at);
        Notification::assertSentTo($booking->planner, SupplierOrderAnswered::class);
    }

    public function test_vendor_must_give_a_reason_to_decline(): void
    {
        Notification::fake();
        [$booking, $vendor, $assignment] = $this->bookingWithVendorOrder();
        $assignment->markOrderSent();

        $this->actingAs($vendor)->post("/vendor/bookings/{$booking->id}/respond", ['decision' => 'decline'])
            ->assertSessionHasErrors('note');
        $this->actingAs($vendor)->post("/vendor/bookings/{$booking->id}/respond", ['decision' => 'decline', 'note' => 'Fully booked that day'])
            ->assertSessionHasNoErrors();

        $assignment->refresh();
        $this->assertSame('unavailable', $assignment->status);
        $this->assertSame('Fully booked that day', $assignment->response_note);
    }

    public function test_vendor_cannot_answer_an_order_that_was_not_sent_or_belongs_to_someone_else(): void
    {
        [$booking, $vendor, $assignment] = $this->bookingWithVendorOrder();

        $this->actingAs($vendor)->post("/vendor/bookings/{$booking->id}/respond", ['decision' => 'accept'])
            ->assertSessionHasErrors('decision');
        $this->assertSame('pending', $assignment->fresh()->status);

        $otherVendor = User::factory()->vendor()->create();
        Supplier::factory()->create(['user_id' => $otherVendor->id]);
        $this->actingAs($otherVendor)->post("/vendor/bookings/{$booking->id}/respond", ['decision' => 'accept'])
            ->assertForbidden();
    }

    public function test_changing_a_sent_order_needs_the_vendor_to_accept_again(): void
    {
        [$booking, $vendor, $assignment] = $this->bookingWithVendorOrder();
        $assignment->recordReply(true, null);
        $assignment->update(['order_sent_at' => now()]);
        $item = $booking->bookingProducts()->first();

        $this->actingAs($booking->planner)->patch("/bookings/{$booking->id}/items/{$item->id}", ['quantity' => 5])
            ->assertSessionHasNoErrors()->assertSessionHas('status', fn ($s) => str_contains($s, 'Send it again'));

        $assignment->refresh();
        $this->assertSame('pending', $assignment->status);
        $this->assertNull($assignment->order_sent_at);
    }

    public function test_notification_link_marks_it_read_and_opens_the_booking(): void
    {
        [$booking, $vendor, $assignment] = $this->bookingWithVendorOrder();
        $assignment->markOrderSent();
        $vendor->notify(new SupplierOrderSent($assignment, $booking->bookingProducts()->with('product')->get()));
        $notification = $vendor->notifications()->first();

        $this->actingAs($vendor)->get('/notifications')->assertOk()->assertSee('New order to accept');
        $this->actingAs($vendor)->get("/notifications/{$notification->id}")->assertRedirect(route('vendor.bookings.show', $booking));
        $this->assertNotNull($notification->fresh()->read_at);
    }
}
