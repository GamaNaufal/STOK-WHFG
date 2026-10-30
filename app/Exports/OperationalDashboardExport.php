<?php

namespace App\Exports;

use App\Exports\Sheets\DashboardChartSheet;
use App\Exports\Sheets\DashboardDataSheet;
use App\Services\OperationalDashboardService;
use App\Services\WarehouseReportService;
use App\Support\AuditLogPresenter;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class OperationalDashboardExport implements WithMultipleSheets
{
    public function __construct(public readonly array $data) {}

    public function sheets(): array
    {
        $d = $this->data;
        $label = fn ($scope) => OperationalDashboardService::SCOPES[$scope] ?? 'Umum';
        $pallet = fn ($row) => $row->scope === 'export' ? ($row->pallet_number ? 'Pallet '.$row->pallet_number : ($row->pallet_id ? 'Pallet cadangan' : 'Belum ditempatkan')) : ($row->pallet_number ?? '—');
        $stock = collect($d['stockTotals'])->map(function ($row, $scope) use ($d, $label) {
            $domain = $d['domains'][$scope];
            $orders = $d['orders']->where('scope', $scope);
            $issues = $d['issues']->where('scope', $scope);

            return [$label($scope), (int) $row->boxes, (int) $row->pcs, (int) $row->pallets, (int) $row->parts, (int) $row->unplaced,
                (int) $domain['inbound']->boxes, (int) $domain['inbound']->pcs, (int) $domain['outbound']->boxes, (int) $domain['outbound']->pcs,
                $orders->count(), (int) $orders->sum('is_full'), $issues->count(), (int) $issues->sum('is_open')];
        })->values()->all();
        $sheets = [new DashboardDataSheet('Ringkasan', ['Lingkup', 'Box saat ini', 'PCS saat ini', 'Pallet berisi stok', 'Jumlah part', 'Box belum ditempatkan',
            'Masuk periode (box)', 'Masuk periode (PCS)', 'Keluar periode (box)', 'Keluar periode (PCS)', 'Permintaan sesuai filter', 'Permintaan penuh', 'Issue sesuai periode', 'Issue masih terbuka'], $stock),
            new DashboardDataSheet('Filter', ['Lingkup', 'Periode', 'Tanggal mulai', 'Tanggal akhir', 'Kelompok grafik', 'Status pemenuhan', 'Pemenuhan penuh (%)', 'Jenis audit', 'Aksi audit', 'ID petugas audit', 'Catatan stok'], [[
                $label($d['filters']['scope']), $d['rangeLabel'], $d['start']?->format('Y-m-d'), $d['end']?->format('Y-m-d'), $d['filters']['group_by'], $d['filters']['fulfillment_filter'], $d['fulfillmentRate'],
                $d['filters']['audit_type'], $d['filters']['audit_action'], $d['filters']['audit_user'], 'Stok saat ini tidak mengikuti periode. Reservasi belum dihitung terpenuhi; pallet siap belum dihitung keluar.']]),
            new DashboardDataSheet('Stok', ['Lingkup', 'Nomor box', 'Nomor part', 'Box', 'PCS', 'Pallet', 'Lokasi', 'Pertama diterima', 'Status'], $d['stockRows']->map(fn ($r) => [
                $label($r->scope), $r->box_number ?? 'Rincian pallet lama', $r->part_number, (int) $r->box_quantity, (int) $r->pcs_quantity, $pallet($r), $r->warehouse_location, $r->occurred_at, WarehouseReportService::statusLabel($r->status)])->all()),
            new DashboardDataSheet('Pallet', ['Lingkup', 'Pallet', 'Lokasi', 'Box', 'PCS', 'Penerimaan tertua'], $d['palletRows']->map(fn ($r) => [
                $label($r->scope), $pallet($r), $r->warehouse_location, (int) $r->boxes, (int) $r->pcs, $r->first_received_at])->all()),
            new DashboardDataSheet('Pemenuhan', ['Lingkup', 'Rencana', 'Customer', 'Tenggat', 'Satuan', 'Kebutuhan', 'Terpenuhi', 'Reservasi (box)', 'Pallet', 'Pallet siap', 'Status'], $d['orders']->map(fn ($r) => [
                $label($r->scope), (string) ($r->reference ?: 'RD-'.$r->record_id), $r->customer_name, $r->deadline, $r->unit, (int) $r->required, (int) $r->fulfilled,
                $r->reserved === null ? null : (int) $r->reserved, $r->pallet_count === null ? null : (int) $r->pallet_count, $r->ready_pallets === null ? null : (int) $r->ready_pallets, WarehouseReportService::statusLabel($r->status)])->all()),
            new DashboardDataSheet('Durasi', ['Lingkup', 'Rencana', 'Customer', 'Proses', 'Mulai', 'Selesai', 'Durasi (menit)', 'Scan (menit)', 'Petugas'], $d['processing']->map(fn ($r) => [
                $label($r->scope), (string) $r->reference, $r->customer_name, $r->phase, $r->started_at, $r->completed_at, $r->duration_min, $r->scan_duration_min, $r->operator_name])->all()),
            new DashboardDataSheet('Issue Scan', ['Lingkup', 'Rencana', 'Nomor box / kode', 'Jenis', 'Status', 'Catatan', 'Waktu'], $d['issues']->map(fn ($r) => [
                $label($r->scope), (string) $r->reference, $r->box_number, $r->issue_type, $r->status, $r->notes, $r->occurred_at])->all()),
            new DashboardDataSheet('Audit', ['Lingkup', 'Jenis', 'Aksi', 'Model', 'ID', 'Deskripsi', 'Petugas', 'Waktu'], $d['auditLogs']->map(fn ($r) => [
                $label($r->scope), AuditLogPresenter::typeLabel($r->type), AuditLogPresenter::actionLabel($r->action), $r->model, $r->model_id === null ? null : (int) $r->model_id, $r->description, $r->user_name, $r->occurred_at])->all()),
        ];
        foreach ($d['domains'] as $domain) {
            foreach ($domain['charts'] as $chart) {
                $name = $domain['label'].' '.['flow' => 'Arus', 'hours' => 'Jam', 'fulfillment' => 'Pemenuhan'][$chart['key']];
                $sheets[] = new DashboardChartSheet($name, $domain['unit'], $chart);
            }
        }

        return $sheets;
    }
}
