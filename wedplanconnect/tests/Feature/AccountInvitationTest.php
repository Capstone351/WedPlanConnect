<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\AccountInvitation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AccountInvitationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Creates a planner as the Admin and returns them with the token from their set-up email.
     *
     * @return array{0: User, 1: string}
     */
    private function createdByAdmin(): array
    {
        Notification::fake();

        $this->actingAs(User::factory()->admin()->create())
            ->post('/admin/users', ['name' => 'Ana Reyes', 'email' => 'ana@example.com', 'role' => 'planner'])
            ->assertSessionHas('status', fn ($s) => str_contains($s, "We've emailed ana@example.com a link to set their password."));
        $this->post('/logout');

        $user = User::where('email', 'ana@example.com')->sole();
        $token = null;
        Notification::assertSentTo($user, AccountInvitation::class, function (AccountInvitation $n) use (&$token) {
            $token = $n->token;

            return true;
        });

        return [$user, $token];
    }

    public function test_the_admin_form_asks_for_no_password_and_says_an_email_is_sent(): void
    {
        $this->actingAs(User::factory()->admin()->create())->get('/admin/users/create')->assertOk()
            ->assertDontSee('id="password"', false)->assertSee('set their own password');
    }

    public function test_the_new_user_sets_their_own_password_from_the_emailed_link_and_logs_in(): void
    {
        [$user, $token] = $this->createdByAdmin();

        $this->get("/set-password/{$token}?email=ana@example.com")->assertOk()->assertSee('Choose your password')->assertSee('Set my password');

        $this->post('/set-password', [
            'token' => $token, 'email' => 'ana@example.com', 'password' => 'MyOwn123', 'password_confirmation' => 'MyOwn123',
        ])->assertRedirect('/login')->assertSessionHas('status', 'Your password is set. You can now log in.');

        $this->post('/login', ['email' => 'ana@example.com', 'password' => 'MyOwn123'])->assertRedirect();
        $this->assertAuthenticatedAs($user);
    }

    public function test_the_link_works_for_three_days_and_only_once(): void
    {
        [, $token] = $this->createdByAdmin();
        $setPassword = fn () => $this->post('/set-password', [
            'token' => $token, 'email' => 'ana@example.com', 'password' => 'MyOwn123', 'password_confirmation' => 'MyOwn123',
        ]);

        // Still valid after two days, longer than a normal one-hour reset link.
        $this->travel(2)->days();
        $setPassword()->assertSessionHasNoErrors();

        $this->post('/logout');
        $setPassword()->assertSessionHasErrors(['email' => 'This link has expired or was already used. Please ask the administrator to send a new one.']);
    }

    public function test_an_expired_link_fails_and_the_admin_can_send_a_new_one(): void
    {
        [$user, $token] = $this->createdByAdmin();

        $this->travel(4)->days();
        $this->post('/set-password', [
            'token' => $token, 'email' => 'ana@example.com', 'password' => 'MyOwn123', 'password_confirmation' => 'MyOwn123',
        ])->assertSessionHasErrors('email');

        $this->actingAs(User::factory()->admin()->create())->get("/admin/users/{$user->id}/edit")->assertSee('Send password set-up link');
        $this->actingAs(User::factory()->admin()->create())->post("/admin/users/{$user->id}/invite")
            ->assertSessionHas('status', 'A new password set-up link has been emailed to ana@example.com.');
        Notification::assertSentToTimes($user, AccountInvitation::class, 2);
    }
}
