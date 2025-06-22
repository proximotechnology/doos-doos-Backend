<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ResetPasswordMail extends Mailable
{
    use Queueable, SerializesModels;

    public $newPassword;

    /**
     * Create a new message instance.
     *
     * @param string $newPassword
     * @return void
     */
    public function __construct(string $newPassword)
    {
        $this->newPassword = $newPassword;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->view('emails.reset-password') // Create this view file
                    ->with(['newPassword' => $this->newPassword])
                    ->subject('Your New Password');
    }
}
