<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class WhatsAppRemindersMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Collection $appuntamenti
    ) {}

    public function build(): self
    {
        return $this
            ->subject('Promemoria WhatsApp appuntamenti di domani')
            ->view('emails.whatsapp-reminders');
    }
}