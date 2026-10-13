<?php

namespace App\Services;

use App\Models\DeliveryPickItem;
use App\Models\DeliveryPickSession;
use Illuminate\Support\Facades\DB;

class PickingVerificationActivityService
{
    public const TIMEOUT_MINUTES = 10;

    public const EXPIRED_MESSAGE = 'Sesi verifikasi dibatalkan otomatis setelah 10 menit tanpa aktivitas. Lock box telah dilepas. Silakan mulai verifikasi kembali.';

    // Caller must hold a row lock on the session inside a transaction.
    public function expireLocked(DeliveryPickSession $session): bool
    {
        if ($session->status !== 'scanning'
            || ! $session->verification_last_activity_at
            || $session->verification_last_activity_at->gt(now()->subMinutes(self::TIMEOUT_MINUTES))
            || $session->issues()->where('status', 'pending')->exists()) {
            return false;
        }

        DeliveryPickItem::where('pick_session_id', $session->id)->delete();
        $session->status = 'cancelled';
        $session->verification_box_ids = null;
        $session->verification_last_activity_at = null;
        $session->approval_notes = trim(($session->approval_notes ? $session->approval_notes.' | ' : '').self::EXPIRED_MESSAGE);
        $session->save();

        return true;
    }

    public function expireDue(): int
    {
        $expired = 0;
        DeliveryPickSession::where('status', 'scanning')
            ->where('verification_last_activity_at', '<=', now()->subMinutes(self::TIMEOUT_MINUTES))
            ->select('id')->chunkById(100, function ($sessions) use (&$expired) {
                foreach ($sessions as $candidate) {
                    $expired += (int) DB::transaction(function () use ($candidate) {
                        $session = DeliveryPickSession::whereKey($candidate->id)->lockForUpdate()->first();

                        return $session && $this->expireLocked($session);
                    });
                }
            });

        return $expired;
    }
}
