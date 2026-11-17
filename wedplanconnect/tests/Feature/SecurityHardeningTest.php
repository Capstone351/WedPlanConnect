<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\InventoryItem;
use App\Models\Payment;
use App\Models\Task;
use App\Models\User;
use App\Notifications\BookingCancelled;
use App\Notifications\BookingChanged;
use App\Notifications\PaymentVoidAnswered;
use App\Notifications\PaymentVoided;
use App\Notifications\PaymentVoidRequested;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    // --- Email outbox -------------------------------------------------------

    public function test_only_the_admin_can_open_the_email_outbox(): void
    {
        config(['mail.default' => 'log']);

        $this->actingAs(User::factory()->planner()->create())->get('/outbox')->assertForbidden();
        $this->actingAs(User::factory()->planner()->create())->get('/planner')->assertDontSee('Email outbox');
        $this->actingAs(User::factory()->admin()->create())->get('/outbox')->assertOk();
    }

    // --- Login --------------------------------------------------------------

    public function test_wrong_passwords_never_reveal_whether_an_account_is_locked_or_deactivated(): void
    {
        $locked = User::factory()->create(['locked_until' => now()->addMinutes(10)]);
        $deactivated = User::factory()->create();
        $deactivated->delete();
        $generic = 'These credentials do not match our records.';

        foreach (['nobody@example.com', $locked->email, $deactivated->email] as $email) {
            $this->post('/login', ['email' => $email, 'password' => 'wrong-password'])->assertSessionHasErrors(['email' => $generic]);
        }

        // Only someone with the right password learns the account's state.
        $this->post('/login', ['email' => $locked->email, 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertStringContainsString('Forgot password', session('errors')->first('email'));
        $this->assertGuest();
    }

    public function test_the_fifth_failure_locks_quietly_and_a_password_reset_unlocks(): void
    {
        $user = User::factory()->create();

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => $user->email, 'password' => 'wrong-password'])
                ->assertSessionHasErrors(['email' => 'These credentials do not match our records.']);
        }

        $this->assertTrue($user->fresh()->isLocked());
    }

    // --- Email verification ------------------------------------------------

    public function test_new_couples_get_a_verification_email_and_must_verify_before_booking_or_meeting(): void
    {
        Notification::fake();
        $planner = User::factory()->planner()->create();

        $this->post('/register', [
            'name' => 'Ana Santos', 'bride_first_name' => 'Ana', 'bride_last_name' => 'Santos', 'groom_first_name' => 'Miguel', 'groom_last_name' => 'Reyes', 'email' => 'ana@example.com',
            'phone' => '09171234567', 'password' => 'secret123', 'password_confirmation' => 'secret123', 'privacy' => '1',
        ])->assertRedirect('/my');

        $couple = User::where('email', 'ana@example.com')->sole();
        $this->assertFalse($couple->hasVerifiedEmail());
        Notification::assertSentTo($couple, VerifyEmail::class);

        $couple->forceFill(['preferred_planner_id' => $planner->id, 'wedding_date' => today()->addMonths(6)])->save();
        $this->get('/my')->assertSee('Please verify your email address');
        $this->post('/my/booking-request')->assertSessionHasErrors('booking');
        $this->post('/my/meetings', ['date' => today()->addWeek()->toDateString(), 'time' => '10:00', 'mode' => 'in_person'])->assertSessionHasErrors('meeting');
        $this->assertSame(0, Booking::count());

        // The emailed link verifies the address, even on a phone that isn't logged in.
        $link = URL::temporarySignedRoute('verification.verify', now()->addHour(), ['id' => $couple->id, 'hash' => sha1($couple->email)]);
        $this->post('/logout');
        $this->get($link)->assertRedirect('/login');
        $this->assertTrue($couple->fresh()->hasVerifiedEmail());

        $this->actingAs($couple->fresh())->post('/my/booking-request')->assertSessionHasNoErrors();
        $this->assertSame(1, Booking::count());
    }

    public function test_a_tampered_verification_link_is_rejected(): void
    {
        $couple = User::factory()->unverified()->create();
        $link = URL::temporarySignedRoute('verification.verify', now()->addHour(), ['id' => $couple->id, 'hash' => sha1('someone-else@example.com')]);

        $this->get($link)->assertForbidden();
        $this->get("/email/verify/{$couple->id}/".sha1($couple->email))->assertForbidden();
        $this->assertFalse($couple->fresh()->hasVerifiedEmail());
    }

    // --- Booking changes ----------------------------------------------------

    public function test_changing_a_confirmed_booking_needs_a_reason_is_recorded_and_tells_the_couple(): void
    {
        Notification::fake();
        $booking = Booking::factory()->confirmed()->create(['total_amount' => 150000, 'venue' => 'Casa Gorordo Museum']);
        $payload = fn (array $o = []) => [
            'client_id' => $booking->client_id, 'client_name' => $booking->client_name, 'contact_number' => '09171234567',
            'event_date' => $booking->event_date->toDateString(), 'venue' => 'Casa Gorordo Museum', 'package' => $booking->package,
            'total_amount' => 150000, ...$o,
        ];

        $this->actingAs($booking->planner)->put("/bookings/{$booking->id}", $payload(['total_amount' => 180000]))->assertSessionHasErrors('change_reason');
        $this->assertSame(150000.0, (float) $booking->fresh()->total_amount);

        $newDate = today()->addMonths(8)->toDateString();
        $this->actingAs($booking->planner)->put("/bookings/{$booking->id}", $payload(['total_amount' => 180000, 'event_date' => $newDate, 'change_reason' => 'Added 20 guests, agreed on May 3.']))
            ->assertSessionHasNoErrors();

        $this->assertSame(['event_date', 'total_amount'], $booking->changes()->reorder('field')->pluck('field')->all());
        $change = $booking->changes()->where('field', 'total_amount')->sole();
        $this->assertSame(['150000.00', '180000.00', 'Added 20 guests, agreed on May 3.', $booking->planner_id], [$change->old_value, $change->new_value, $change->reason, $change->user_id]);
        $this->assertSame($newDate, $booking->client->fresh()->wedding_date->toDateString());
        Notification::assertSentTo($booking->client, BookingChanged::class, fn ($n) => $n->changes->count() === 2 && $n->reason === 'Added 20 guests, agreed on May 3.');

        $this->actingAs($booking->planner)->get("/bookings/{$booking->id}")->assertSee('Change history')->assertSee('₱180,000.00', false)->assertSee('Added 20 guests');
    }

    public function test_the_contract_amount_cannot_drop_below_what_is_paid(): void
    {
        $booking = Booking::factory()->create(['total_amount' => 100000, 'venue' => 'Casa Gorordo Museum']);
        Payment::create(['booking_id' => $booking->id, 'amount' => 60000, 'payment_date' => today(), 'method' => 'cash']);

        $this->actingAs($booking->planner)->put("/bookings/{$booking->id}", [
            'client_id' => $booking->client_id, 'client_name' => $booking->client_name, 'contact_number' => '09171234567',
            'event_date' => $booking->event_date->toDateString(), 'venue' => 'Casa Gorordo Museum', 'total_amount' => 50000,
        ])->assertSessionHasErrors('total_amount');

        $this->assertSame(100000.0, (float) $booking->fresh()->total_amount);
    }

    public function test_cancelling_needs_a_reason_archives_open_tasks_and_tells_the_couple(): void
    {
        Notification::fake();
        $booking = Booking::factory()->confirmed()->create();
        $open = Task::create(['booking_id' => $booking->id, 'assigned_to' => $booking->planner_id, 'title' => 'Call florist', 'due_date' => today()->addWeek()]);

        $this->actingAs($booking->planner)->patch("/bookings/{$booking->id}/cancel", ['reason' => 'The couple postponed.'])->assertSessionHasNoErrors();

        $this->assertSoftDeleted($open);
        $this->assertSame('The couple postponed.', $booking->changes()->where('field', 'status')->sole()->reason);
        Notification::assertSentTo($booking->client, BookingCancelled::class, fn ($n) => $n->reason === 'The couple postponed.');
    }

    public function test_a_wedding_cannot_be_completed_before_its_date(): void
    {
        $booking = Booking::factory()->confirmed()->create(['event_date' => today()->addMonth()]);

        $this->actingAs($booking->planner)->patch("/bookings/{$booking->id}/complete")->assertSessionHasErrors('status');
        $this->assertSame('confirmed', $booking->fresh()->status);
    }

    public function test_unconfirmed_bookings_release_the_planners_date_after_the_hold_period(): void
    {
        Notification::fake();
        $booking = Booking::factory()->create(['event_date' => today()->addMonths(4)]);

        $this->travel(config('wedplan.pending_hold_days') + 1)->days();
        $this->artisan('bookings:release-expired')->assertSuccessful();

        $this->assertSame('cancelled', $booking->fresh()->status);
        $this->assertNull($booking->changes()->sole()->user_id);
        Notification::assertSentTo($booking->client, BookingCancelled::class);
        $this->assertNotContains($booking->planner_id, Booking::plannerIdsBookedOn($booking->event_date)->all());
    }

    public function test_pending_bookings_inside_the_hold_period_still_hold_the_date(): void
    {
        $booking = Booking::factory()->create(['event_date' => today()->addMonths(4)]);

        $this->travel(config('wedplan.pending_hold_days') - 1)->days();

        $this->assertContains($booking->planner_id, Booking::plannerIdsBookedOn($booking->event_date)->all());
        $this->assertSame('pending', $booking->fresh()->status);
    }

    // --- Payments -----------------------------------------------------------

    public function test_voiding_a_payment_needs_a_reason_stays_on_record_and_tells_the_couple(): void
    {
        Notification::fake();
        $booking = Booking::factory()->confirmed()->create(['total_amount' => 100000]);

        $this->actingAs($booking->planner)->post('/payments', ['booking_id' => $booking->id, 'amount' => 30000, 'method' => 'cash', 'receipt_number' => 'OR-9']);
        $payment = Payment::sole();
        $this->assertSame($booking->planner_id, $payment->recorded_by);

        $admin = User::factory()->admin()->create();

        // The planner can't void it; they ask the admin, with a reason.
        $this->actingAs($booking->planner)->patch("/admin/payments/{$payment->id}/void")->assertForbidden();
        $this->actingAs($booking->planner)->get("/payments?booking_id={$booking->id}")->assertOk()->assertSee('Ask admin to void');
        $this->actingAs($booking->planner)->post("/payments/{$payment->id}/void-request")->assertSessionHasErrors('void_reason');
        $this->actingAs($booking->planner)->post("/payments/{$payment->id}/void-request", ['void_reason' => 'Recorded twice by mistake.'])->assertSessionHasNoErrors();
        $this->assertNotSoftDeleted($payment);
        Notification::assertSentTo($admin, PaymentVoidRequested::class);
        $this->actingAs($booking->planner)->get("/payments?booking_id={$booking->id}")->assertSee('Void requested · waiting for admin');
        $this->actingAs($booking->planner)->post("/payments/{$payment->id}/void-request", ['void_reason' => 'Again.'])->assertSessionHasErrors('void_reason');

        // The admin sees it in the action centre and voids it.
        $this->actingAs($admin)->get('/admin')->assertOk()->assertSee('asks to void ₱30,000.00', false)->assertSee('Recorded twice by mistake.');
        $this->actingAs($admin)->patch("/admin/payments/{$payment->id}/void")->assertSessionHasNoErrors();

        $this->assertSoftDeleted($payment);
        $this->assertSame([$admin->id, 'Recorded twice by mistake.', null], [$payment->fresh()->voided_by, $payment->fresh()->void_reason, $payment->fresh()->void_requested_at]);
        Notification::assertSentTo($booking->client, PaymentVoided::class);
        Notification::assertSentTo($booking->planner, PaymentVoidAnswered::class, fn ($n) => $n->voided);
        $this->actingAs($admin)->patch("/admin/payments/{$payment->id}/void")->assertNotFound();

        $this->actingAs($booking->planner)->get("/payments?booking_id={$booking->id}")->assertOk()
            ->assertSee('Voided by')->assertSee('Recorded twice by mistake.')->assertSee('OR-9');
    }

    public function test_admin_can_keep_a_payment_a_planner_asked_to_void(): void
    {
        Notification::fake();
        $booking = Booking::factory()->confirmed()->create(['total_amount' => 100000]);
        $payment = Payment::create(['booking_id' => $booking->id, 'amount' => 5000, 'payment_date' => today(), 'method' => 'cash', 'recorded_by' => $booking->planner_id]);
        $this->actingAs($booking->planner)->post("/payments/{$payment->id}/void-request", ['void_reason' => 'Wrong amount?'])->assertSessionHasNoErrors();

        $this->actingAs(User::factory()->admin()->create())->patch("/admin/payments/{$payment->id}/keep", ['note' => 'The receipt shows it was paid.'])->assertSessionHasNoErrors();

        $this->assertNotSoftDeleted($payment);
        $this->assertFalse($payment->fresh()->hasVoidRequest());
        Notification::assertSentTo($booking->planner, PaymentVoidAnswered::class, fn ($n) => ! $n->voided && $n->note === 'The receipt shows it was paid.');
        Notification::assertNotSentTo($booking->client, PaymentVoided::class);
    }

    // --- Cross-planner access ----------------------------------------------

    public function test_planners_cannot_tie_stock_or_portfolio_entries_to_another_planners_wedding(): void
    {
        $other = Booking::factory()->create();
        $planner = User::factory()->planner()->create();
        $item = InventoryItem::create(['name' => 'Chairs', 'category' => 'Furniture', 'quantity' => 50, 'unit' => 'pcs']);

        $this->actingAs($planner)->post("/inventory/{$item->id}/adjust", ['direction' => 'out', 'amount' => 5, 'booking_id' => $other->id])->assertForbidden();
        $this->assertSame(50, $item->fresh()->quantity);

        $this->actingAs($planner)->get("/portfolio/create?booking_id={$other->id}")->assertForbidden();
        $this->actingAs($planner)->post('/portfolio', ['title' => 'Ana & Miguel', 'booking_id' => $other->id])->assertForbidden();
    }

    // --- Headers and debug --------------------------------------------------

    public function test_pages_send_security_headers_and_hide_debug_details_from_other_devices(): void
    {
        config(['app.debug' => true]);

        $this->get('/login')->assertHeader('X-Frame-Options', 'SAMEORIGIN')->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');

        $this->get('http://wedplan.example.ngrok.app/login');
        $this->assertFalse(config('app.debug'));
    }
}
