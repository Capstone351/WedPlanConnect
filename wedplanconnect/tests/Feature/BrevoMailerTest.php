<?php

namespace Tests\Feature;

use App\Models\OutboxMessage;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Bridge\Brevo\Transport\BrevoApiTransport;
use Tests\TestCase;

class BrevoMailerTest extends TestCase
{
    public function test_the_brevo_mailer_sends_through_brevos_api_with_the_configured_key(): void
    {
        config(['mail.default' => 'brevo', 'services.brevo.key' => 'test-key']);

        $transport = Mail::mailer('brevo')->getSymfonyTransport();

        $this->assertInstanceOf(BrevoApiTransport::class, $transport);
        $this->assertSame('brevo+api://api.brevo.com', (string) $transport);
    }

    public function test_the_test_mode_outbox_is_off_once_real_email_is_used(): void
    {
        config(['mail.default' => 'brevo']);
        $this->assertFalse(OutboxMessage::enabled());

        config(['mail.default' => 'log']);
        $this->assertTrue(OutboxMessage::enabled());
    }
}
