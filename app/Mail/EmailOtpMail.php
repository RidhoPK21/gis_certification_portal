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
        $subject = match ($this->purpose) {
            EmailOtp::PURPOSE_ADMIN_INVITE => 'Aktivasi Akun Staf SystemGIS',
            EmailOtp::PURPOSE_PASSWORD_RESET => 'Reset Kata Sandi SystemGIS',
            default => 'Kode Verifikasi Email SystemGIS',
        };

        return $this->subject($subject)->view('emails.email-otp');
    }
}
