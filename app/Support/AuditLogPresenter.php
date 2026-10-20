<?php

namespace App\Support;

use App\Models\AuditLog;
use Carbon\CarbonImmutable;

class AuditLogPresenter
{
    private const FIELDS = [
        'box_number' => 'Nomor box', 'pallet_number' => 'Nomor pallet',
        'delivery_order_id' => 'Delivery order', 'part_number' => 'Nomor part',
        'pcs_quantity' => 'Jumlah PCS', 'box_quantity' => 'Jumlah box',
        'total_pcs_quantity' => 'Total PCS', 'total_box_quantity' => 'Total box',
        'total_boxes' => 'Total box', 'completed_boxes' => 'Box selesai', 'box_count' => 'Jumlah box',
        'warehouse_location' => 'Lokasi gudang', 'location' => 'Lokasi gudang',
        'pallet' => 'Pallet', 'stored_at' => 'Tanggal masuk', 'withdrawn_at' => 'Tanggal pengambilan',
        'age_months' => 'Umur stok (bulan)', 'handled_at' => 'Tanggal Handle',
        'reactivated_until' => 'Aktif sampai',
        'qty_box' => 'PCS per box', 'name' => 'Nama', 'email' => 'Email',
        'role' => 'Role', 'is_active' => 'Akun aktif', 'password_changed' => 'Sandi diubah',
        'request_type' => 'Jenis permintaan', 'issue_type' => 'Jenis issue',
        'scanned_code' => 'Kode scan', 'session_status' => 'Status sesi',
        'verification' => 'Mode verifikasi',
        'verified_count' => 'Box diverifikasi', 'notes' => 'Catatan',
        'status' => 'Status', 'completion_status' => 'Status penyelesaian',
        'deleted' => 'Dihapus dari stok aktif', 'soft_deleted_box_count' => 'Box dihapus dari stok aktif',
        'hard_deleted_box_count' => 'Box dihapus permanen',
        'detached_shared_box_count' => 'Box dilepas dari pallet (tetap ada di pallet lain)',
        'assigned_box_count' => 'Box dialokasikan', 'assigned_existing_box_count' => 'Box stok dialokasikan',
        'created_new_box_count' => 'Box baru dibuat', 'pallets' => 'Pallet terkait',
        'auto_deleted_pallets' => 'Pallet kosong yang ikut dihapus',
    ];

    public static function actionLabel(?string $action): string
    {
        return match ($action) {
            'created' => 'Dibuat', 'updated' => 'Diperbarui', 'completed' => 'Diselesaikan',
            'reversed' => 'Dibatalkan / dikembalikan', 'redo' => 'Ulangi delivery',
            'moved' => 'Dipindahkan', 'merged' => 'Pallet digabung',
            'handled' => 'Box di-Handle',
            'approved' => 'Disetujui', 'rejected' => 'Ditolak',
            'started' => 'Dimulai', 'cancelled' => 'Dibatalkan',
            'verified' => 'Diverifikasi', 'scanned' => 'Dipindai',
            'terminated' => 'Lock dihentikan', 'timed_out' => 'Waktu habis',
            'deleted' => 'Dihapus', 'deactivated' => 'Dinonaktifkan',
            'box_updated_by_admin_warehouse' => 'Edit Box (Admin Warehouse)',
            'box_deleted_by_stock_view' => 'Hapus Box (Stock View)',
            'pallet_deleted_by_stock_view' => 'Hapus Pallet (Stock View)',
            default => ucfirst(str_replace('_', ' ', $action ?? 'Aktivitas')),
        };
    }

    public static function typeLabel(?string $type): string
    {
        return match ($type) {
            'stock_input' => 'Input stok', 'stock_withdrawal' => 'Pengambilan stok',
            'delivery_pickup' => 'Pengiriman', 'delivery_assignment' => 'Assignment delivery',
            'delivery_redo' => 'Redo delivery', 'box_pallet_moved' => 'Pemindahan box',
            'expired_box' => 'Penanganan box expired',
            'not_full_box' => 'Box Not Full', 'delivery_pick' => 'Picking delivery',
            'delivery_issue' => 'Issue scan delivery',
            'master_location' => 'Master lokasi', 'master_part' => 'Master part',
            'user_management' => 'Kelola user', 'profile' => 'Profil pengguna',
            'role_feature_access' => 'Hak akses role',
            'pallet_location_moved' => 'Pemindahan lokasi pallet', 'pallet_merged' => 'Penggabungan pallet',
            default => 'Aktivitas stok',
        };
    }

    public static function present(AuditLog $log): array
    {
        // Read historical snapshots only; current inventory may have changed or been deleted.
        $old = self::normalize($log->old_values);
        $new = self::normalize($log->new_values);
        if ($log->model === 'DeliveryOrder' && !isset($new['delivery_order_id'])) {
            $new['delivery_order_id'] = $log->model_id;
        }
        $changes = $facts = $tables = [];
        foreach (self::FIELDS as $key => $label) {
            $hasOld = array_key_exists($key, $old);
            $hasNew = array_key_exists($key, $new);
            if (!$hasOld && !$hasNew) {
                continue;
            }
            $before = self::format($key, $old[$key] ?? null);
            $after = self::format($key, $new[$key] ?? null);
            if ($hasOld && $hasNew && $before !== $after) {
                $changes[] = compact('label', 'before', 'after');
            } else {
                // Missing keys in partial snapshots do not mean the field was cleared.
                $facts[] = ['label' => $label.(!$hasNew ? ' (sebelum aksi)' : ''), 'value' => $hasNew ? $after : $before];
            }
        }

        foreach (['old' => $old, 'new' => $new] as $side => $values) {
            foreach (['boxes', 'items', 'overage_adjustments'] as $key) {
                if (!is_array($values[$key] ?? null) || !$values[$key]) {
                    continue;
                }
                $isAdjustment = $key === 'overage_adjustments';
                $columns = $isAdjustment
                    ? ['part_number' => 'Nomor part', 'old_quantity' => 'Sebelum (PCS)', 'new_quantity' => 'Sesudah (PCS)', 'increased_by' => 'Tambahan (PCS)']
                    : ['box_number' => 'Nomor box', 'part_number' => 'Nomor part', 'pcs_quantity' => 'Jumlah PCS', 'warehouse_location' => 'Lokasi gudang'];
                $rows = [];
                foreach ($values[$key] as $item) {
                    if (!is_array($item)) {
                        continue;
                    }
                    $row = [];
                    foreach ($columns as $field => $label) {
                        $row[] = array_key_exists($field, $item) ? self::format($field, $item[$field]) : 'Tidak tercatat';
                    }
                    $rows[] = $row;
                }
                if ($rows) {
                    $tables[] = [
                        'title' => ($isAdjustment ? 'Penyesuaian jumlah delivery' : 'Rincian box').($side === 'old' ? ' sebelum aksi' : ' saat aksi'),
                        'columns' => array_values($columns), 'rows' => $rows,
                    ];
                }
            }
        }

        $reason = $new['reason'] ?? $old['reason'] ?? null;
        if (is_string($reason) && $reason !== $log->description) {
            $facts[] = ['label' => 'Alasan', 'value' => $reason === 'auto_deleted_after_last_box_removed' ? 'Box terakhir pada pallet telah dihapus.' : $reason];
        }

        return [
            'action' => self::actionLabel($log->action), 'type' => self::typeLabel($log->type),
            'description' => $log->description ?: 'Tidak ada catatan tambahan.',
            'changes' => $changes, 'facts' => $facts, 'tables' => $tables,
        ];
    }

    private static function normalize(mixed $json): array
    {
        $values = is_string($json) ? json_decode($json, true) : $json;
        if (!is_array($values)) {
            return [];
        }
        if (!isset($values['part_number']) && is_array($values['part_numbers'] ?? null)) {
            $values['part_number'] = implode(', ', array_filter($values['part_numbers'], 'is_scalar'));
        }
        foreach (['assigned_box', 'assigned_existing_box', 'created_new_box'] as $prefix) {
            if (is_array($values[$prefix.'_ids'] ?? null)) {
                $values[$prefix.'_count'] = count($values[$prefix.'_ids']);
            }
        }
        foreach (['from_pallet', 'to_pallet'] as $key) {
            if (array_key_exists($key, $values)) {
                $values['pallet'] = $values[$key];
            }
        }
        return $values;
    }

    private static function format(string $key, mixed $value): string
    {
        if ($value === null || $value === '') {
            return 'Belum diisi';
        }
        if (in_array($key, ['pallets', 'auto_deleted_pallets'], true) && is_array($value)) {
            $numbers = array_filter(array_map(fn ($item) => is_array($item) ? ($item['pallet_number'] ?? null) : null, $value), 'is_scalar');
            return $numbers ? implode(', ', $numbers) : (count($value) ? count($value).' pallet (nomor tidak tercatat)' : 'Tidak ada');
        }
        if (!is_scalar($value)) {
            return 'Tidak tercatat';
        }
        if ($key === 'deleted') {
            return $value ? 'Ya' : 'Tidak';
        }
        if (in_array($key, ['is_active', 'password_changed', 'verification'], true)) {
            return $value ? 'Ya' : 'Tidak';
        }
        if (in_array($key, ['stored_at', 'withdrawn_at', 'handled_at', 'reactivated_until'], true)) {
            try {
                return CarbonImmutable::parse($value)->format('d/m/Y H:i:s');
            } catch (\Throwable) {
                return 'Tanggal tidak valid';
            }
        }
        if (in_array($key, ['status', 'completion_status', 'session_status'], true)) {
            return match ((string) $value) {
                'pending' => 'Menunggu', 'completed' => 'Selesai', 'partial' => 'Sebagian selesai',
                'in_progress' => 'Sedang diproses', 'cancelled', 'canceled' => 'Dibatalkan',
                'reversed' => 'Dikembalikan', 'active' => 'Aktif', 'expired' => 'Kedaluwarsa',
                'warning' => 'Peringatan', 'approved' => 'Disetujui', 'rejected' => 'Ditolak',
                'scanning' => 'Sedang dipindai', 'blocked' => 'Diblokir',
                default => ucfirst(str_replace('_', ' ', (string) $value)),
            };
        }
        if ($key === 'delivery_order_id') {
            return 'DO #'.$value;
        }
        if (is_numeric($value) && (str_contains($key, 'quantity') || str_ends_with($key, '_count') || in_array($key, ['total_boxes', 'completed_boxes', 'increased_by'], true))) {
            return number_format((float) $value, 0, ',', '.');
        }
        return (string) $value;
    }
}
