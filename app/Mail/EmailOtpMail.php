<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class EmailOtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $otp;
    public string $name;

    public function __construct(string $otp, $name)
    {
        $this->otp = $otp;
        $this->name = $name;
    }

    public function build()
    {
        return $this->subject('Your Email Verification OTP')
            ->view('emails.email_otp');
    }
}
