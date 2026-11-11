<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ExpiredBoxSummaryMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public array $sections)
    {
    }

    public function build()
    {
        return $this->subject('[Warehouse FG] Ringkasan Umur Box Lokal dan Ekspor')
            ->view('emails.expired-box-summary');
    }
}
