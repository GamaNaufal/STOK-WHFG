<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

trait ClaimsDeliveryPlanCode
{
    protected function registerPlanCode(): void
    {
        $registry = $this->getConnection()->table('export_delivery_plan_codes');
        $registry->insertOrIgnore(['code' => $this->plan_code, 'export_delivery_plan_id' => $this->id, 'created_at' => now()]);
        if ((int) $this->getConnection()->table('export_delivery_plan_codes')->where('code', $this->plan_code)->value('export_delivery_plan_id') !== (int) $this->id) {
            throw ValidationException::withMessages(['plan_code' => 'Kode rencana delivery sudah pernah digunakan oleh rencana lain, termasuk kode lama yang telah diubah.']);
        }
    }

    protected function performInsert(Builder $query)
    {
        return $this->getConnection()->transaction(function () use ($query) {
            $result = parent::performInsert($query);
            $this->registerPlanCode();

            return $result;
        });
    }

    protected function performUpdate(Builder $query)
    {
        return $this->getConnection()->transaction(function () use ($query) {
            $this->registerPlanCode();

            return parent::performUpdate($query);
        });
    }
}
