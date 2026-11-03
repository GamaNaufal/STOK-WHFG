<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;

trait ClaimsGlobalBoxIdentity
{
    public static function bootClaimsGlobalBoxIdentity(): void
    {
        static::updating(function ($box) {
            if ($box->isDirty('box_number')) {
                throw \Illuminate\Validation\ValidationException::withMessages(['box_number' => 'Identitas box tidak dapat diganti setelah tercatat.']);
            }
        });
    }

    protected function performInsert(Builder $query)
    {
        return $this->getConnection()->transaction(function () use ($query) {
            if (! $this->adoptsLegacyIdentity()) {
                $registry = $this->getConnection()->table('box_identities');
                if ($registry->where('box_number', $this->box_number)->exists()) {
                    throw new \RuntimeException('ID Box sudah pernah digunakan pada domestik atau ekspor, termasuk histori yang dihapus.');
                }
                try {
                    $this->getConnection()->table('box_identities')->insert(['box_number' => $this->box_number, 'origin_scope' => static::INVENTORY_SCOPE]);
                } catch (UniqueConstraintViolationException $error) {
                    throw new \RuntimeException('ID Box sudah digunakan pada domestik atau ekspor.', 0, $error);
                }
            }

            return parent::performInsert($query);
        });
    }

    protected function adoptsLegacyIdentity(): bool
    {
        return false;
    }
}
