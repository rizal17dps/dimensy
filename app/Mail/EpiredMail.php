<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class EpiredMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($subject, $name, $date, $template = null)
    {
        //
        $this->subject = $subject;
        $this->name = $name;
        $this->date = $date;
        if($template != null){
            $this->template = $template;
        } else {
            $this->template = 'sign';
        }
        
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
                        'name' => $this->name,
                        'date' => $this->date
                    ]);
    }
}
