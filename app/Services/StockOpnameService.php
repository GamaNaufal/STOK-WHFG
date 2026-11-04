<?php

namespace App\Services;

use App\Models\{StockOpnameItem, StockOpnameScan, StockOpnameSession, User};
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockOpnameService
{
    public function __construct(private StockOpnameInventory $inventory) {}

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['opname' => $message]);
    }

    public function start(string $scope, string $token, User $actor): StockOpnameSession
    {
        if ($previous = StockOpnameSession::where('start_token', $token)->first()) {
            if ($previous->scope !== $scope || $previous->started_by !== $actor->id) $this->fail('Token sesi sudah digunakan.');
            return $previous;
        }
        if ($active = StockOpnameSession::where('active_scope', $scope)->first()) return $active;
        try {
            return DB::transaction(function () use ($scope, $token, $actor) {
                $session = StockOpnameSession::create(['scope' => $scope, 'active_scope' => $scope, 'start_token' => $token,
                    'started_by' => $actor->id, 'started_at' => now()]);
                $session->update(['source_max_id' => $this->inventory->catalogue($scope)->max('id') ?? 0]);
                $query = $this->inventory->onHand($this->inventory->catalogue($scope), $scope);
                $query->chunkById(500, function ($boxes) use ($session, $scope) {
                    $rows = $boxes->map(function ($box) use ($session, $scope) {
                        $snapshot = json_encode($this->inventory->snapshot($box, $scope), JSON_THROW_ON_ERROR);
                        return ['stock_opname_session_id' => $session->id, 'box_number' => $box->box_number,
                            'initial_snapshot' => $snapshot, 'current_snapshot' => $snapshot, 'present' => true,
                            'change_kind' => 'unchanged', 'created_at' => now(), 'updated_at' => now()];
                    })->all();
                    DB::table('stock_opname_items')->insert($rows);
                });
                $session->update(['summary' => $this->summary($session)]);
                AuditService::record($this->auditType($session), 'started', 'StockOpnameSession', $session->id, $actor->id,
                    [], ['scope' => $scope, 'reference' => $session->code, 'total_boxes' => $session->items()->count()], 'Stock opname '.$session->code.' dimulai untuk lingkup '.$session->scope_label.'.');
                return $session;
            }, 3);
        } catch (UniqueConstraintViolationException $error) {
            // The unique active_scope also protects starts from different tabs/operators.
            $active = StockOpnameSession::where('active_scope', $scope)->first();
            if ($active) return $active;
            $previous = StockOpnameSession::where('start_token', $token)->where('scope', $scope)->where('started_by', $actor->id)->first();
            if ($previous) return $previous;
            throw $error;
        }
    }

    private function auditType(StockOpnameSession $session): string
    {
        return $session->scope === 'export' ? 'export_stock_opname' : 'stock_opname';
    }

    private function updateItem(StockOpnameSession $session, string $number, array $snapshot): StockOpnameItem
    {
        $item = $session->items()->firstOrNew(['box_number' => $number]);
        $initial = $item->initial_snapshot;
        $kind = $this->changeKind($initial, $snapshot);
        $item->fill(['current_snapshot' => $snapshot, 'present' => $snapshot['present'], 'change_kind' => $kind])->save();
        return $item;
    }

    public function scan(int $sessionId, string $input, string $token, User $actor): StockOpnameScan
    {
        return DB::transaction(function () use ($sessionId, $input, $token, $actor) {
            $session = StockOpnameSession::lockForUpdate()->findOrFail($sessionId);
            if ($replay = StockOpnameScan::where('request_token', $token)->first()) {
                if ($replay->stock_opname_session_id !== $session->id || $replay->scanned_by !== $actor->id || $replay->input !== $input) {
                    $this->fail('Token scan sudah dipakai untuk scan yang berbeda.');
                }
                return $replay;
            }
            if ($session->status !== 'active') $this->fail('Sesi sudah selesai. Scan baru tidak dapat dicatat.');
            $snapshot = null;
            $outcome = 'ng';
            $reason = 'ID Box harus tepat 8 angka.';
            if (preg_match('/^[0-9]{8}$/D', $input)) {
                $box = $this->inventory->catalogue($session->scope)->where('box_number', $input)->first();
                if ($box) {
                    $snapshot = $this->inventory->snapshot($box, $session->scope);
                    if ($snapshot['present']) {
                        $item = $this->updateItem($session, $input, $snapshot);
                        $outcome = $item->first_scanned_at ? 'duplicate' : 'ok';
                        $reason = $outcome === 'duplicate' ? 'Sudah terscan. Jumlah box tidak ditambah.'
                            : ($item->initial_snapshot === null ? 'Sesuai. Box masuk selama sesi opname.' : 'Sesuai dengan stok '.$session->scope_label.'.');
                        if (!$item->first_scanned_at) $item->update(['first_scanned_at' => now(), 'first_scanned_by' => $actor->id]);
                    } else {
                        $reason = $snapshot['reason'];
                        if ($session->items()->where('box_number', $input)->exists()) $this->updateItem($session, $input, $snapshot);
                    }
                } else {
                    $origin = DB::table('box_identities')->where('box_number', $input)->value('origin_scope');
                    $reason = $origin && $origin !== $session->scope
                        ? 'ID Box terdaftar pada lingkup '.($origin === 'export' ? 'Ekspor' : 'Lokal').'.'
                        : ($origin ? 'ID Box memiliki histori tetapi sudah tidak ada dalam stok gudang.' : 'ID Box tidak ditemukan dalam sistem.');
                    $item = $session->items()->where('box_number', $input)->first();
                    if ($item) {
                        $snapshot = array_replace($item->current_snapshot, ['present' => false, 'reason' => $reason]);
                        $this->updateItem($session, $input, $snapshot);
                    }
                }
            }
            return $session->scans()->create(['request_token' => $token, 'input' => $input, 'outcome' => $outcome,
                'reason' => $reason, 'snapshot' => $snapshot, 'scanned_by' => $actor->id, 'scanned_at' => now()]);
        }, 3);
    }

    public function reconcile(StockOpnameSession $session): void
    {
        $observed = $this->inventory->observed($session);
        $items = $session->items()->get()->keyBy('box_number');
        foreach ($items as $item) {
            $snapshot = $observed[$item->box_number] ?? array_replace($item->current_snapshot, [
                'present' => false, 'reason' => 'Box sudah tidak tercatat dalam stok gudang.',
            ]);
            $observed[$item->box_number] = $snapshot;
        }
        foreach (array_chunk($observed, 500, true) as $chunk) {
            $rows = [];
            foreach ($chunk as $number => $snapshot) {
                $initial = $items->get($number)?->initial_snapshot;
                $rows[] = ['stock_opname_session_id' => $session->id, 'box_number' => (string) $number,
                    'initial_snapshot' => $initial === null ? null : json_encode($initial, JSON_THROW_ON_ERROR),
                    'current_snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR), 'present' => $snapshot['present'],
                    'change_kind' => $this->changeKind($initial, $snapshot), 'created_at' => now(), 'updated_at' => now()];
            }
            DB::table('stock_opname_items')->upsert($rows, ['stock_opname_session_id', 'box_number'],
                ['current_snapshot', 'present', 'change_kind', 'updated_at']);
        }
    }

    private function changeKind(?array $initial, array $snapshot): string
    {
        if ($initial === null) return $snapshot['present'] ? 'received' : 'received_left';
        if (!$snapshot['present']) return 'left';
        return $initial === $snapshot ? 'unchanged' : 'changed';
    }

    public function finish(int $sessionId, User $actor): StockOpnameSession
    {
        return DB::transaction(function () use ($sessionId, $actor) {
            $session = StockOpnameSession::lockForUpdate()->findOrFail($sessionId);
            if ($session->status === 'completed') return $session;
            $this->reconcile($session);
            $summary = $this->summary($session);
            $session->update(['status' => 'completed', 'active_scope' => null, 'finished_by' => $actor->id,
                'finished_at' => now(), 'summary' => $summary]);
            AuditService::record($this->auditType($session), 'completed', 'StockOpnameSession', $session->id, $actor->id,
                [], ['scope' => $session->scope, 'reference' => $session->code] + $summary, 'Stock opname '.$session->code.' selesai. Hasil pemeriksaan disimpan.');
            return $session;
        }, 3);
    }

    public function refresh(int $sessionId): StockOpnameSession
    {
        return DB::transaction(function () use ($sessionId) {
            $session = StockOpnameSession::lockForUpdate()->findOrFail($sessionId);
            if ($session->status === 'active') {
                $this->reconcile($session);
                $session->update(['summary' => $this->summary($session)]);
            }
            return $session;
        }, 3);
    }

    public function summary(StockOpnameSession $session): array
    {
        if ($session->status === 'completed') return $session->summary;
        $items = $session->items()->selectRaw('COUNT(*) AS tracked,
            COALESCE(SUM(CASE WHEN initial_snapshot IS NOT NULL THEN 1 ELSE 0 END), 0) AS baseline,
            COALESCE(SUM(CASE WHEN present = 1 THEN 1 ELSE 0 END), 0) AS present,
            COALESCE(SUM(CASE WHEN present = 1 AND first_scanned_at IS NOT NULL THEN 1 ELSE 0 END), 0) AS found,
            COALESCE(SUM(CASE WHEN present = 1 AND first_scanned_at IS NULL THEN 1 ELSE 0 END), 0) AS missing,
            COALESCE(SUM(CASE WHEN present = 0 THEN 1 ELSE 0 END), 0) AS departed,
            COALESCE(SUM(CASE WHEN initial_snapshot IS NULL THEN 1 ELSE 0 END), 0) AS arrivals')->first()->getAttributes();
        $counts = array_map('intval', $items);
        $scans = $session->scans()->selectRaw('outcome, COUNT(*) AS total')->groupBy('outcome')->pluck('total', 'outcome');
        $counts['ng'] = (int) ($scans['ng'] ?? 0);
        $counts['duplicates'] = (int) ($scans['duplicate'] ?? 0);
        $counts['scans'] = (int) $scans->sum();
        return $counts;
    }
}
