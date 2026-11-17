<?php

namespace App\Exports;

use App\Exports\Sheets\StockOpnameSheet;
use App\Models\StockOpnameSession;
use App\Services\StockOpnamePartNumbers;
use Generator;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use PhpOffice\PhpSpreadsheet\Shared\Date;

class StockOpnameExport implements WithMultipleSheets
{
    private const SCAN_LABELS = ['ok' => 'OK', 'ng' => 'NG', 'duplicate' => 'Duplikat'];
    private const CHANGE_LABELS = [
        'unchanged' => 'Sesuai stok awal', 'changed' => 'Posisi / data berubah',
        'received' => 'Masuk selama sesi', 'left' => 'Keluar / ID diganti',
        'received_left' => 'Masuk lalu keluar / ID diganti',
    ];
    private StockOpnamePartNumbers $partNumbers;

    public function __construct(private readonly StockOpnameSession $session)
    {
        $this->partNumbers = new StockOpnamePartNumbers;
    }

    public function sheets(): array
    {
        // Preserve the three established tabs and add focused review views.
        return [
            new StockOpnameSheet('Ringkasan', ['Keterangan', 'Nilai'], $this->summaryRows(), ['A' => 38, 'B' => 76], ['B5:B5', 'B7:B7', 'B22:B22']),
            $this->scanSheet('Semua Scan'),
            $this->itemSheet('Rekonsiliasi Box'),
            new StockOpnameSheet('Rekap per Part', [
                'Nomor part dokumen', 'Nomor part label / scan', 'Acuan nomor dokumen',
                'Box stok awal', 'PCS stok awal', 'Box stok akhir', 'PCS stok akhir',
                'Box sesuai', 'PCS sesuai', 'Box belum terscan', 'PCS belum terscan',
                'Box masuk selama sesi', 'PCS masuk selama sesi', 'Box keluar / ID diganti',
                'PCS keluar / ID diganti', 'Box data berubah',
            ], $this->partRows(), ['A' => 40, 'B' => 26, 'C' => 60]),
            $this->scanSheet('Scan OK', 'ok'),
            $this->scanSheet('Scan Error NG', 'ng'),
            $this->scanSheet('Scan Duplikat', 'duplicate'),
            $this->itemSheet('Belum Terscan', 'missing'),
            $this->itemSheet('Box Sesuai', 'found'),
            $this->itemSheet('Perubahan Data', 'changed'),
            $this->itemSheet('Box Masuk', 'received'),
            $this->itemSheet('Box Keluar dan ID Diganti', 'left'),
        ];
    }

    private function scanSheet(string $title, ?string $outcome = null): StockOpnameSheet
    {
        return new StockOpnameSheet($title, [
            'No.', 'Waktu scan (WIB)', 'Operator', 'Input scan / ID Box', 'Hasil scan', 'Alasan hasil scan',
            'Nomor part dokumen', 'PCS', 'Pallet saat scan', 'Lokasi saat scan', 'Status stok saat scan',
            'Ada di stok saat scan', 'Keterangan stok saat scan', 'Nomor part label / scan',
            'Acuan nomor dokumen', 'Penggunaan ID Box',
        ], $this->scanRows($outcome), [
            'C' => 28, 'D' => 30, 'F' => 56, 'G' => 40, 'I' => 24, 'J' => 28, 'M' => 54, 'N' => 26, 'O' => 64,
        ], ['B']);
    }

    private function itemSheet(string $title, ?string $section = null): StockOpnameSheet
    {
        return new StockOpnameSheet($title, [
            'ID Box', 'Hasil rekonsiliasi', 'Perubahan selama sesi', 'Ada di stok akhir', 'Scan pertama (WIB)', 'Operator scan pertama',
            'Nomor part dokumen awal', 'PCS awal', 'Pallet awal', 'Lokasi awal', 'Status stok awal',
            'Nomor part dokumen akhir', 'PCS akhir', 'Pallet akhir', 'Lokasi akhir', 'Status stok akhir', 'Keterangan stok akhir',
            'Nomor part label / scan awal', 'Nomor part label / scan akhir',
            'Acuan nomor dokumen awal', 'Acuan nomor dokumen akhir', 'Penggunaan ID Box awal', 'Penggunaan ID Box akhir',
        ], $this->itemRows($section), [
            'B' => 26, 'C' => 35, 'F' => 28, 'G' => 40, 'I' => 24, 'J' => 28, 'L' => 40,
            'N' => 24, 'O' => 28, 'Q' => 54, 'R' => 28, 'S' => 28, 'T' => 64, 'U' => 64,
        ], ['E']);
    }

    private function summaryRows(): array
    {
        $summary = $this->session->summary;
        $rows = [
            ['Kode sesi', $this->session->code], ['Lingkup', $this->session->scope_label], ['Status sesi', 'Selesai'],
            ['Dimulai (WIB)', Date::dateTimeToExcel($this->session->started_at)], ['Dimulai oleh', $this->session->starter?->name],
            ['Selesai (WIB)', $this->session->finished_at ? Date::dateTimeToExcel($this->session->finished_at) : null],
            ['Diselesaikan oleh', $this->session->finisher?->name],
        ];
        foreach ([
            'baseline' => 'Box di stok awal', 'tracked' => 'Total box yang dibandingkan',
            'present' => 'Box di stok akhir', 'found' => 'Box sesuai di stok akhir', 'missing' => 'Box belum terscan',
            'arrivals' => 'Box masuk selama sesi', 'departed' => 'Box keluar / ID diganti',
            'scans' => 'Total scan', 'ng' => 'Scan NG', 'duplicates' => 'Scan duplikat',
        ] as $key => $label) $rows[] = [$label, $summary[$key] ?? null];
        $rows[] = ['Scan OK', $summary['scans'] - $summary['ng'] - $summary['duplicates']];
        $rows[] = ['Box data berubah', $this->session->items()->where('change_kind', 'changed')->count()];
        $rows[] = ['Acuan nomor dokumen', 'Memakai nomor dokumen yang tercatat saat pemeriksaan. Catatan lama yang belum memilikinya memakai pemetaan Master Part saat ini, ditandai pada setiap baris.'];
        $rows[] = ['Pemetaan master ditinjau (WIB)', Date::dateTimeToExcel(now())];
        $rows[] = ['Cara membaca rincian', 'Tab rincian adalah kelompok dari Semua Scan atau Rekonsiliasi Box. Jangan menjumlahkan seluruh tab karena catatan yang sama dapat tampil di beberapa kelompok.'];
        $rows[] = ['Jumlah scan dan jumlah box', 'Jumlah scan adalah jumlah percobaan. Scan duplikat tidak menambah jumlah box sesuai.'];
        $rows[] = ['Box sesuai', 'Box masih ada di stok akhir dan sudah terscan. Posisi atau data box tetap dapat berubah selama sesi.'];
        $rows[] = ['Rekap per Part', 'Dikelompokkan berdasarkan part label / scan. Beberapa nomor dokumen ditampilkan dalam satu kolom, bukan dihitung sebagai part atau box tambahan.'];
        $rows[] = ['Box masuk dan keluar', 'Box yang masuk lalu keluar selama sesi tercantum pada kedua tab. Box Keluar dan ID Diganti mencatat yang tidak ada di stok akhir.'];
        return $rows;
    }

    private function scanRows(?string $outcome = null): Generator
    {
        $number = 0;
        $query = $this->session->scans()->with('scanner')->when($outcome, fn ($query) => $query->where('outcome', $outcome));
        foreach ($query->lazyById(500)->chunk(500) as $scans) {
            $this->partNumbers->primeSnapshots($scans->pluck('snapshot'));
            foreach ($scans as $scan) {
                $snapshot = $this->partNumbers->present($scan->snapshot);
                yield [
                    ++$number, Date::dateTimeToExcel($scan->scanned_at), $scan->scanner?->name, $scan->input,
                    self::SCAN_LABELS[$scan->outcome] ?? $scan->outcome, $scan->reason, ...$this->stockColumns($snapshot),
                    isset($snapshot['present']) ? ($snapshot['present'] ? 'Ya' : 'Tidak') : null, $snapshot['reason'] ?? null,
                    $snapshot['part_number'] ?? null, $snapshot['document_number_note'] ?? null, $snapshot['identity_cycle'] ?? null,
                ];
            }
        }
    }

    private function items(?string $section = null): iterable
    {
        $query = $this->session->items()->with('scanner');
        match ($section) {
            'missing' => $query->where('present', true)->whereNull('first_scanned_at'),
            'found' => $query->where('present', true)->whereNotNull('first_scanned_at'),
            'changed' => $query->where('change_kind', 'changed'),
            'received' => $query->whereNull('initial_snapshot'),
            'left' => $query->where('present', false),
            default => null,
        };
        foreach ($query->lazyById(500)->chunk(500) as $items) {
            $this->partNumbers->primeSnapshots($items->flatMap(fn ($item) => [$item->initial_snapshot, $item->current_snapshot]));
            foreach ($items as $item) yield [$item, $this->partNumbers->present($item->initial_snapshot), $this->partNumbers->present($item->current_snapshot)];
        }
    }

    private function itemRows(?string $section = null): Generator
    {
        foreach ($this->items($section) as [$item, $initial, $current]) {
            yield [
                $item->box_number, $item->present ? ($item->first_scanned_at ? 'Sesuai' : 'Belum terscan') : 'Keluar / ID diganti',
                self::CHANGE_LABELS[$item->change_kind] ?? $item->change_kind, $item->present ? 'Ya' : 'Tidak',
                $item->first_scanned_at ? Date::dateTimeToExcel($item->first_scanned_at) : null, $item->scanner?->name,
                ...$this->stockColumns($initial), ...$this->stockColumns($current), $current['reason'] ?? null,
                $initial['part_number'] ?? null, $current['part_number'] ?? null,
                $initial['document_number_note'] ?? null, $current['document_number_note'] ?? null,
                $initial['identity_cycle'] ?? null, $current['identity_cycle'] ?? null,
            ];
        }
    }

    private function partRows(): Generator
    {
        $parts = [];
        $add = function (array $snapshot, int $boxColumn, int $pcsColumn) use (&$parts): void {
            $key = 'part:'.($snapshot['part_number'] ?? '');
            $parts[$key] ??= ['number' => $snapshot['part_number'] ?? '', 'documents' => [], 'notes' => [], 'counts' => array_fill(0, 13, 0)];
            $parts[$key]['documents'] = array_values(array_unique([...$parts[$key]['documents'], ...$snapshot['document_part_numbers']]));
            $parts[$key]['notes'][$snapshot['document_number_note']] = true;
            if ($boxColumn >= 0) $parts[$key]['counts'][$boxColumn]++;
            if ($pcsColumn >= 0) $parts[$key]['counts'][$pcsColumn] += (int) ($snapshot['pcs_quantity'] ?? 0);
        };
        foreach ($this->items() as [$item, $initial, $current]) {
            if ($initial) $add($initial, 0, 1);
            $snapshot = $current ?? $initial;
            if (! $snapshot) continue;
            $add($snapshot, -1, -1);
            if ($item->present) {
                $add($snapshot, 2, 3);
                $item->first_scanned_at ? $add($snapshot, 4, 5) : $add($snapshot, 6, 7);
            }
            if ($initial === null) $add($snapshot, 8, 9);
            if (! $item->present) $add($snapshot, 10, 11);
            if ($item->change_kind === 'changed') $add($snapshot, 12, -1);
        }
        uksort($parts, 'strnatcmp');
        $totals = array_fill(0, 13, 0);
        foreach ($parts as $part) {
            foreach ($part['counts'] as $index => $count) $totals[$index] += $count;
            yield [implode(' / ', $part['documents']), $part['number'], implode('; ', array_keys($part['notes'])), ...$part['counts']];
        }
        if ($parts) yield ['TOTAL', null, 'Setiap box dihitung sekali pada masing masing ukuran pemeriksaan.', ...$totals];
    }

    private function stockColumns(?array $snapshot): array
    {
        return [$snapshot['document_part_number'] ?? null, $snapshot['pcs_quantity'] ?? null, $snapshot['pallet'] ?? null,
            $snapshot['location'] ?? null, $snapshot['status'] ?? null];
    }
}
