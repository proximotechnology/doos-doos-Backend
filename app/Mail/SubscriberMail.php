<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SubscriberMail extends Mailable
{
    use Queueable, SerializesModels;

    public $subscriberName; // إذا بدك اسم المشترك
    public $welcomeMessage;

    /**
     * Create a new message instance.
     */
    public function __construct($subscriberName = null)
    {
        $this->subscriberName = $subscriberName;
        $this->welcomeMessage = "Welcome to our newsletter! Thank you for subscribing 🎉";
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Welcome to the Doos family!',
        );
    }

    /**
     * Build the message.
     */
    public function build()
    {
        return $this->view('mail.Welcome') // خلي عندك view باسم mail/welcome.blade.php
            ->with([
                'subscriberName' => $this->subscriberName,
                'welcomeMessage' => $this->welcomeMessage
            ]);
    }

    /**
     * Get the attachments for the message.
     */
    public function attachments(): array
    {
        return [];
    }
}
