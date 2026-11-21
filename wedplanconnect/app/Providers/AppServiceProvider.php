<?php

namespace App\Providers;

use App\Models\OutboxMessage;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\Mailer\Bridge\Brevo\Transport\BrevoTransportFactory;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mime\Address;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // MAIL_MAILER=brevo sends through Brevo's API using BREVO_API_KEY.
        Mail::extend('brevo', fn () => (new BrevoTransportFactory)->create(
            new Dsn('brevo+api', 'default', (string) config('services.brevo.key'))
        ));

        // Test mode: keep a readable copy of each email (e.g. QR one-time PINs, password links)
        // for the staff Email outbox, since nothing is actually delivered.
        Event::listen(MessageSent::class, function (MessageSent $event) {
            if (! OutboxMessage::enabled()) {
                return;
            }

            $message = $event->message;

            OutboxMessage::create([
                'to' => collect($message->getTo())->map(fn (Address $a) => $a->getAddress())->join(', '),
                'subject' => (string) $message->getSubject(),
                'html' => $message->getHtmlBody() ?? nl2br(e((string) $message->getTextBody())),
            ]);

            OutboxMessage::where('id', '<=', OutboxMessage::max('id') - OutboxMessage::KEEP)->delete();
        });
    }
}
