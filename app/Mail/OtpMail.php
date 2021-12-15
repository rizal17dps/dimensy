<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class OtpMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($subject, $otp)
    {
        //
        $this->subject = $subject;
        $this->otp = $otp;
        $this->template = 'otp';
        
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->from(env('MAIL_FROM_ADDRESS'), 'Mail')
                    ->subject($this->subject)
                    ->markdown('emails.'.$this->template)
                    ->with([
                        'otp' => $this->otp
                    ]);
    }
}
