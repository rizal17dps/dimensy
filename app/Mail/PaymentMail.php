<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PaymentMail extends Mailable
{
    use Queueable, SerializesModels;

    protected $mailData;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($mailData)
    {
        //
        $this->mailData = $mailData;        
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $address = $this->mailData->to;
        $subject = $this->mailData->subject;
        $name = $this->mailData->name;
        $from = env("MAIL_FROM_ADDRESS");
        return $this->view('emails.payment')
            ->from($from, $name)
            ->replyTo($from, $name)
            ->subject($subject)
            ->with(['mailMessage' => $this->mailData]);;
    }
}
