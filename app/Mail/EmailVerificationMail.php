<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EmailVerificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public $verificationLink;

    /**
     * Create a new message instance.
     */
    public function __construct($verificationLink)
    {
        $this->verificationLink = $verificationLink;
    }

    /**
     * Build the message.
     */
    public function build()
    {
        return $this->subject('تحقق من بريدك الإلكتروني')
                    ->view('emails.verify')
                    ->with(['verificationLink' => $this->verificationLink]);
    }
}
