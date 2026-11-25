<?php

namespace App\Services;

use App\Models\PartDocumentNumber;
use App\Models\PartSetting;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class PartDocumentNumberService
{
    /** Called inside the inventory mutex, together with the master save and audit. */
    public function sync(PartSetting $part, array $data): void
    {
        // Older clients changing only capacity must not erase document mappings.
        if (! array_key_exists('document_numbers', $data) && ! array_key_exists('same_document_number', $data)) {
            return;
        }
        $numbers = ! empty($data['same_document_number']) ? [] : array_values(array_unique(array_filter(
            array_map('trim', preg_split('/\R/u', $data['document_numbers'] ?? '')), fn ($number) => $number !== ''
        )));
        if (count($numbers) > 100) {
            $this->fail('Maksimal 100 kode dokumen. Isi satu kode per baris.');
        }
        if (array_key_exists('same_document_number', $data) && empty($data['same_document_number']) && ! $numbers) {
            $this->fail('Isi kode dokumen atau pilih sama dengan nomor part label / scan.');
        }
        foreach ($numbers as $number) {
            if (mb_strlen($number) > 100) {
                $this->fail('Setiap kode dokumen maksimal 100 karakter.');
            }
            if (PartSetting::withTrashed()->where('part_number', $number)->where('id', '!=', $part->id)->exists()
                || PartDocumentNumber::where('document_number', $number)->where('part_setting_id', '!=', $part->id)->exists()) {
                $this->fail('Kode dokumen '.$number.' sudah mengacu ke master lain. Periksa identitas produk dan stok sebelum memetakan.');
            }
        }
        $part->documentNumbers()->whereNotIn('document_number', $numbers)->delete();
        foreach ($numbers as $number) {
            $part->documentNumbers()->firstOrCreate(['document_number' => $number]);
        }
        $part->unsetRelation('documentNumbers');
    }

    public function assertScanNumberAvailable(string $number, ?int $id = null): void
    {
        if (PartDocumentNumber::where('document_number', $number)->when($id, fn ($q) => $q->where('part_setting_id', '!=', $id))->exists()) {
            throw ValidationException::withMessages(['part_number' => 'Kode scan sudah digunakan sebagai kode dokumen master lain.']);
        }
    }

    public function resolve(string $number, Collection $parts): ?PartSetting
    {
        $matches = $parts->filter(fn ($part) => $part->part_number === $number
            || $part->documentNumbers->contains(fn ($alias) => $alias->document_number === $number));
        if ($matches->count() > 1) {
            $this->fail('Kode '.$number.' ambigu dan mengacu ke beberapa master. Periksa pemetaan terlebih dahulu.');
        }

        return $matches->first();
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['document_numbers' => $message]);
    }
}
