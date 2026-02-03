<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ExpiredBoxSummaryMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public $warningBoxes, public $expiredBoxes)
    {
    }

    public function build()
    {
        return $this->subject('Peringatan Box Mendekati Expired')
            ->view('emails.expired-box-summary');
    }
}
