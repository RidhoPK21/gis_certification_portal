<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PortalEventMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public string $mailTitle,
        public string $mailMessage,
        public ?string $actionUrl = null
    ) {
    }

    public function build(): self
    {
        return $this->subject($this->mailTitle)->view('emails.portal-event');
    }
}
