<?php

namespace App\Services;

use App\Mail\ExpiredBoxSummaryMail;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use InvalidArgumentException;

class ExpiredBoxSummaryService
{
    public function __construct(
        private ExpiredBoxService $local,
        private ExportExpiredBoxService $export,
    ) {}

    public function sendDailySummary(): bool
    {
        $override = trim((string) config('mail.expired_box_summary_to'));
        if ($override !== '' && ! filter_var($override, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('EXPIRED_BOX_SUMMARY_TO harus berisi satu alamat email yang valid.');
        }
        $recipients = $override !== '' ? [$override] : User::where('role', 'supervisi')
            ->whereNotNull('email')->distinct()->pluck('email')->all();

        if ($recipients === []) {
            return false;
        }

        $sections = [];
        foreach (['Lokal' => $this->local, 'Ekspor' => $this->export] as $scope => $service) {
            $service->syncStatuses();
            $table = $scope === 'Lokal' ? 'boxes' : 'b';
            $sections[$scope] = $service->getExpirableBoxesQuery()
                ->whereIn($table.'.expired_status', ['warning', 'expired'])
                ->orderBy('stored_at')->orderBy($table.'.box_number')
                ->get()->unique('id')->groupBy('expired_status');
        }

        if (collect($sections)->every(fn ($boxes) => $boxes->isEmpty())) {
            return false;
        }

        Mail::to($recipients)->send(new ExpiredBoxSummaryMail($sections));

        return true;
    }
}
