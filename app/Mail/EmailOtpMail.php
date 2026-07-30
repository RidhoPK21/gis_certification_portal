<?php

namespace App\Mail;

use App\Models\EmailOtp;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class EmailOtpMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public User $recipientUser,
        public string $code,
        public string $purpose
    ) {
    }

    public function build(): self
    {
        $subject = $this->purpose === EmailOtp::PURPOSE_ADMIN_INVITE
            ? 'Aktivasi Akun Staf SystemGIS'
            : 'Kode Verifikasi Email SystemGIS';

        return $this->subject($subject)->view('emails.email-otp');
    }
}
