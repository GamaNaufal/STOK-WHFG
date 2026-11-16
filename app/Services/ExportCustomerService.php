<?php

namespace App\Services;

use App\Models\{ExportCustomer, ExportCustomerAlias, User};
use Illuminate\Validation\ValidationException;

class ExportCustomerService
{
    public static function normalize(string $text): string
    {
        return mb_strtolower(preg_replace('/\s+/u', ' ', trim($text)));
    }

    public function options()
    {
        return app(ExportCustomerStockPolicy::class)->ready()
            ? ExportCustomer::where('is_active', true)->orderBy('name')->get() : collect();
    }

    public function active(int $id): ExportCustomer
    {
        $customer = ExportCustomer::whereKey($id)->where('is_active', true)->first();
        if (! $customer) throw ValidationException::withMessages(['customer_id' => 'Customer tidak ditemukan atau sudah nonaktif. Pilih customer aktif.']);
        return $customer;
    }

    public function resolve(string $name, string $code = ''): array
    {
        if (! app(ExportCustomerStockPolicy::class)->ready()) return ['status' => 'unavailable', 'customer_id' => null];
        $byName = ExportCustomerAlias::with('customer')->where('normalized', self::normalize($name))->first();
        $byCode = $code !== '' ? ExportCustomerAlias::with('customer')->where('normalized', self::normalize($code))->where('kind', 'code')->first() : null;
        if (($code !== '' && ! $byCode) || ($byCode && $byName && $byCode->export_customer_id !== $byName->export_customer_id)) {
            return ['status' => 'conflict', 'customer_id' => null, 'message' => 'Kode dan nama customer perlu diperiksa.'];
        }
        $match = $byCode ?? $byName;
        if (! $match || ! $match->customer->is_active) return ['status' => 'unmatched', 'customer_id' => null, 'message' => 'Pilih customer master yang aktif sebelum menyimpan.'];
        return ['status' => 'matched', 'customer_id' => $match->export_customer_id, 'customer_name' => $match->customer->name];
    }

    public function save(array $data, User $actor, ?int $id = null): ExportCustomer
    {
        abort_unless($actor->canAccess('export_customers_manage'), 403);
        app(ExportCustomerReservationService::class)->assertWrites();
        return app(ExportInventoryService::class)->transaction(function () use ($data, $actor, $id) {
            $customer = $id ? ExportCustomer::lockForUpdate()->findOrFail($id) : new ExportCustomer;
            if ($id && $customer->revision !== (int) $data['revision']) throw ValidationException::withMessages(['revision' => 'Master customer berubah. Muat ulang sebelum menyimpan.']);
            $before = $id ? $customer->load('aliases')->toArray() : [];
            $entries = [['label' => trim($data['code']), 'kind' => 'code'], ['label' => trim($data['name']), 'kind' => 'name']];
            foreach (preg_split('/\r?\n/', $data['aliases'] ?? '') as $alias) {
                if (trim($alias) !== '') $entries[] = ['label' => trim($alias), 'kind' => 'alias'];
            }
            $normalized = [];
            foreach ($entries as $entry) {
                $key = self::normalize($entry['label']);
                if ($key === '' || mb_strlen($key) > 255) throw ValidationException::withMessages(['aliases' => 'Nama/kode/alias customer harus 1 sampai 255 karakter.']);
                if (ExportCustomerAlias::where('normalized', $key)->when($id, fn ($q) => $q->where('export_customer_id', '!=', $id))->exists()) throw ValidationException::withMessages(['aliases' => 'Kode/nama/alias '.$entry['label'].' sudah dipakai customer lain.']);
                $normalized[$key] ??= $entry + ['normalized' => $key];
            }
            $customer->fill(['code' => trim($data['code']), 'name' => trim($data['name']), 'is_active' => (bool) ($data['is_active'] ?? false), 'revision' => $id ? $customer->revision + 1 : 1])->save();
            $customer->aliases()->delete();
            $customer->aliases()->createMany(array_values($normalized));
            AuditService::record('export_customer', $id ? 'updated' : 'created', 'ExportCustomer', $customer->id, $actor->id, $before, $customer->fresh('aliases')->toArray(), 'Master customer Ekspor disimpan tanpa mengubah snapshot delivery lama.');
            return $customer;
        });
    }
}
