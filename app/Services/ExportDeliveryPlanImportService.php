<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;
use PhpOffice\PhpSpreadsheet\Reader\Xls;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use ZipArchive;

class ExportDeliveryPlanImportService
{
    private function failure(string $message): never
    {
        throw ValidationException::withMessages(['file' => $message]);
    }

    private function label(mixed $value): string
    {
        return strtolower(preg_replace('/[^a-z0-9]/i', '', (string) $value));
    }

    private function findColumn(array $labels, array $aliases): ?int
    {
        foreach ($labels as $column => $label) {
            if (in_array($label, $aliases, true)) {
                return $column;
            }
        }

        return null;
    }

    private function dateValue(mixed $value): ?string
    {
        if (is_numeric($value) && (float) $value > 20000 && (float) $value < 80000) {
            return Date::excelToDateTimeObject((float) $value)->format('Y-m-d');
        }
        $text = trim((string) $value);
        if (preg_match('/\b(\d{4})-(\d{2})-(\d{2})\b/', $text, $m) && checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            return $m[0];
        }
        if (preg_match('/\b(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})\b/', $text, $m) && checkdate((int) $m[2], (int) $m[1], (int) $m[3])) {
            return sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
        }
        $months = ['januari' => 1, 'january' => 1, 'februari' => 2, 'february' => 2,
            'maret' => 3, 'march' => 3, 'april' => 4, 'mei' => 5, 'may' => 5, 'juni' => 6,
            'june' => 6, 'juli' => 7, 'july' => 7, 'agustus' => 8, 'august' => 8,
            'september' => 9, 'oktober' => 10, 'october' => 10, 'november' => 11, 'desember' => 12, 'december' => 12];
        if (preg_match('/\b(\d{1,2})\s+([a-z]+)\s+(\d{4})\b/i', $text, $m)) {
            $month = $months[strtolower($m[2])] ?? 0;
            if (checkdate($month, (int) $m[1], (int) $m[3])) {
                return sprintf('%04d-%02d-%02d', $m[3], $month, $m[1]);
            }
        }

        return null;
    }

    public function parse(UploadedFile $file): array
    {
        $extension = strtolower($file->getClientOriginalExtension());
        if (! in_array($extension, ['xlsx', 'xls'], true)) {
            $this->failure('Gunakan file Excel .xlsx atau .xls.');
        }
        if ($extension === 'xlsx') {
            $zip = new ZipArchive;
            if ($zip->open($file->getRealPath()) !== true) {
                $this->failure('File Excel tidak dapat dibaca.');
            }
            $size = 0;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $size += $zip->statIndex($i)['size'];
            }
            $count = $zip->numFiles;
            $zip->close();
            if ($size > 32 * 1024 * 1024 || $count > 1500) {
                $this->failure('Isi file Excel terlalu besar. Gunakan template dengan maksimal 500 part.');
            }
        }

        $reader = $extension === 'xlsx' ? new Xlsx : new Xls;
        if (! $reader->canRead($file->getRealPath())) {
            $this->failure('Isi file tidak sesuai dengan format Excel.');
        }
        $reader->setReadDataOnly(true);
        $reader->setReadFilter(new class implements IReadFilter
        {
            public function readCell($columnAddress, $row, $worksheetName = ''): bool
            {
                return $row <= 550 && \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($columnAddress) <= 52;
            }
        });
        $book = null;
        try {
            $info = $reader->listWorksheetInfo($file->getRealPath());
            $nonEmpty = array_values(array_filter($info, fn ($sheet) => $sheet['totalRows'] > 0));
            if (count($nonEmpty) !== 1) {
                $this->failure('Gunakan satu sheet berisi satu customer per file.');
            }
            if (($nonEmpty[0]['totalRows'] ?? 0) > 550 || ($nonEmpty[0]['totalColumns'] ?? 0) > 52) {
                $this->failure('Maksimal 500 baris part. Hapus baris/kolom kosong berlebih dari file.');
            }
            $reader->setLoadSheetsOnly($nonEmpty[0]['worksheetName']);
            $book = $reader->load($file->getRealPath());
            $sheet = $book->getSheet(0);
            $grid = [];
            for ($row = 1; $row <= $sheet->getHighestDataRow(); $row++) {
                $values = [];
                for ($column = 1; $column <= min(52, $nonEmpty[0]['totalColumns']); $column++) {
                    $cell = $sheet->getCell([$column, $row]);
                    $value = $cell->getValue();
                    if ($cell->getDataType() === \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_FORMULA) {
                        // Use the saved Excel result only; never evaluate workbook formulas.
                        $value = $cell->getOldCalculatedValue() ?? $value;
                    }
                    $values[] = $value;
                }
                $grid[] = $values;
            }

            return $this->parseRows($grid, $file->getClientOriginalName());
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            $this->failure('File Excel tidak dapat dibaca. Coba gunakan template yang disediakan.');
        } finally {
            $book?->disconnectWorksheets();
        }
    }

    public function parseRows(array $grid, string $fileName = ''): array
    {
        $customers = [];
        foreach ($grid as $cells) {
            foreach ($cells as $column => $value) {
                $name = null;
                if (preg_match('/^(?:customer|pelanggan)\s*[:：]\s*(.+)$/iu', (string) $value, $match)) {
                    $name = trim($match[1]);
                } elseif (in_array($this->label($value), ['customer', 'pelanggan', 'namacustomer'], true)) {
                    $name = trim((string) ($cells[$column + 1] ?? ''));
                }
                if ($name !== null && $name !== '') {
                    $customers[$name] = true;
                }
            }
        }
        if (count($customers) > 1) {
            $this->failure('File berisi beberapa customer. Gunakan satu customer per file untuk tahap ini.');
        }
        $customer = '';
        $deadline = '';
        $header = null;
        $partColumn = null;
        $quantityColumn = null;
        foreach (array_slice($grid, 0, 40, true) as $index => $cells) {
            foreach ($cells as $column => $value) {
                $label = $this->label($value);
                if (preg_match('/^(?:customer|pelanggan)\s*[:：]\s*(.+)$/iu', (string) $value, $match)) {
                    $customer = trim($match[1]);
                } elseif (in_array($label, ['customer', 'pelanggan', 'namacustomer'], true)) {
                    $customer = trim((string) ($cells[$column + 1] ?? ''));
                }
                if (str_contains($label, 'tanggal') || str_contains($label, 'deadline')) {
                    $deadline = $this->dateValue($cells[$column + 1] ?? null) ?? $this->dateValue($value) ?? $deadline;
                } elseif (! $deadline && is_string($value)) {
                    $deadline = $this->dateValue($value) ?? '';
                }
            }
            $labels = array_map(fn ($value) => $this->label($value), $cells);
            $part = $this->findColumn($labels, ['partno', 'nopart', 'nomorpart', 'partnumber']);
            $quantity = $this->findColumn($labels, ['qtypcs', 'jumlahpcs', 'quantitypcs', 'quantity', 'jumlah', 'qty']);
            $partColumn = $part ?? $partColumn;
            $quantityColumn = $quantity ?? $quantityColumn;
            if ($partColumn !== null && $quantityColumn !== null) {
                $header = $index;
                break;
            }
        }
        if ($header === null) {
            $this->failure('Kolom Nomor Part dan Jumlah PCS tidak ditemukan. Gunakan template Excel.');
        }
        $items = [];
        foreach (array_slice($grid, $header + 1) as $cells) {
            $part = trim((string) ($cells[$partColumn] ?? ''));
            $quantity = $cells[$quantityColumn] ?? '';
            $rowText = $this->label(implode(' ', array_map(fn ($value) => is_scalar($value) ? (string) $value : '', $cells)));
            if (str_starts_with($rowText, 'totalbox') || str_starts_with($rowText, 'totalpallet')) {
                break;
            }
            if ($part === '' && ($quantity === null || $quantity === '')) {
                continue;
            }
            // Formula cells are left as text and must be corrected; nothing is evaluated.
            $items[] = ['part_number' => $part, 'pcs_quantity' => $quantity];
            if (count($items) > 500) {
                $this->failure('Maksimal 500 baris part dalam satu rencana.');
            }
        }
        if (! $items) {
            $this->failure('File belum berisi baris nomor part dan jumlah PCS.');
        }

        return ['customer_name' => $customer, 'delivery_deadline' => $deadline,
            'items' => $items, 'source_name' => mb_substr($fileName, 0, 255)];
    }
}
