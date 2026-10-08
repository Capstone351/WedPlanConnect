<?php

namespace App\Mail;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class QrOtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Booking $booking, public string $otp) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your WedPlanConnect wedding status PIN');
    }

    public function content(): Content
    {
        return new Content(view: 'mail.qr-otp', with: [
            'minutes' => config('wedplan.otp_ttl_minutes'),
        ]);
    }
}
