<?php

namespace App\Exports;

use App\Exports\Sheets\StockOpnameSheet;
use App\Models\StockOpnameSession;
use Generator;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use PhpOffice\PhpSpreadsheet\Shared\Date;

class StockOpnameExport implements WithMultipleSheets
{
    private const SCAN_LABELS = ['ok' => 'OK', 'ng' => 'NG', 'duplicate' => 'Duplikat'];

    private const CHANGE_LABELS = [
        'unchanged' => 'Sesuai stok awal',
        'changed' => 'Posisi / data berubah',
        'received' => 'Masuk selama sesi',
        'left' => 'Keluar / ID diganti',
        'received_left' => 'Masuk lalu keluar / ID diganti',
    ];

    public function __construct(private readonly StockOpnameSession $session) {}

    public function sheets(): array
    {
        return [
            new StockOpnameSheet('Ringkasan', ['Keterangan', 'Nilai'], $this->summaryRows(), ['A' => 36, 'B' => 36], ['B5:B5', 'B7:B7']),
            new StockOpnameSheet('Semua Scan', [
                'No.', 'Waktu scan (WIB)', 'Operator', 'Input scan / ID Box', 'Hasil scan', 'Alasan hasil scan',
                'Nomor part', 'PCS', 'Pallet saat scan', 'Lokasi saat scan', 'Status stok saat scan',
                'Ada di stok saat scan', 'Keterangan stok saat scan',
            ], $this->scanRows(), ['C' => 28, 'D' => 30, 'F' => 56, 'G' => 24, 'I' => 24, 'J' => 28, 'M' => 54], ['B']),
            new StockOpnameSheet('Rekonsiliasi Box', [
                'ID Box', 'Hasil rekonsiliasi', 'Perubahan selama sesi', 'Ada di stok akhir', 'Scan pertama (WIB)', 'Operator scan pertama',
                'Nomor part awal', 'PCS awal', 'Pallet awal', 'Lokasi awal', 'Status stok awal',
                'Nomor part akhir', 'PCS akhir', 'Pallet akhir', 'Lokasi akhir', 'Status stok akhir', 'Keterangan stok akhir',
            ], $this->itemRows(), ['B' => 26, 'C' => 35, 'F' => 28, 'G' => 24, 'I' => 24, 'J' => 28, 'L' => 24, 'N' => 24, 'O' => 28, 'Q' => 54], ['E']),
        ];
    }

    private function summaryRows(): array
    {
        $summary = $this->session->summary;
        $rows = [
            ['Kode sesi', $this->session->code],
            ['Lingkup', $this->session->scope_label],
            ['Status sesi', 'Selesai'],
            ['Dimulai (WIB)', Date::dateTimeToExcel($this->session->started_at)],
            ['Dimulai oleh', $this->session->starter?->name],
            ['Selesai (WIB)', $this->session->finished_at ? Date::dateTimeToExcel($this->session->finished_at) : null],
            ['Diselesaikan oleh', $this->session->finisher?->name],
        ];
        $labels = [
            'baseline' => 'Box di stok awal', 'tracked' => 'Total box yang dibandingkan',
            'present' => 'Box di stok akhir', 'found' => 'Box sesuai di stok akhir', 'missing' => 'Box belum terscan',
            'arrivals' => 'Box masuk selama sesi', 'departed' => 'Box keluar / ID diganti',
            'scans' => 'Total scan', 'ng' => 'Scan NG', 'duplicates' => 'Scan duplikat',
        ];
        foreach ($labels as $key => $label) {
            $rows[] = [$label, $summary[$key] ?? null];
        }
        $rows[] = ['Scan OK', $summary['scans'] - $summary['ng'] - $summary['duplicates']];

        return $rows;
    }

    private function scanRows(): Generator
    {
        $number = 0;
        foreach ($this->session->scans()->with('scanner')->lazyById() as $scan) {
            $snapshot = $scan->snapshot;
            yield [
                ++$number, Date::dateTimeToExcel($scan->scanned_at), $scan->scanner?->name, $scan->input,
                self::SCAN_LABELS[$scan->outcome] ?? $scan->outcome, $scan->reason,
                ...$this->stockColumns($snapshot),
                isset($snapshot['present']) ? ($snapshot['present'] ? 'Ya' : 'Tidak') : null,
                $snapshot['reason'] ?? null,
            ];
        }
    }

    private function itemRows(): Generator
    {
        foreach ($this->session->items()->with('scanner')->lazyById() as $item) {
            yield [
                $item->box_number, $item->present ? ($item->first_scanned_at ? 'Sesuai' : 'Belum terscan') : 'Keluar / ID diganti',
                self::CHANGE_LABELS[$item->change_kind] ?? $item->change_kind, $item->present ? 'Ya' : 'Tidak',
                $item->first_scanned_at ? Date::dateTimeToExcel($item->first_scanned_at) : null, $item->scanner?->name,
                ...$this->stockColumns($item->initial_snapshot),
                ...$this->stockColumns($item->current_snapshot),
                $item->current_snapshot['reason'] ?? null,
            ];
        }
    }

    private function stockColumns(?array $snapshot): array
    {
        return [
            $snapshot['part_number'] ?? null, $snapshot['pcs_quantity'] ?? null, $snapshot['pallet'] ?? null,
            $snapshot['location'] ?? null, $snapshot['status'] ?? null,
        ];
    }
}
