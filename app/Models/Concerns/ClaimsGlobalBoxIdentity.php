<?php

namespace App\Models\Concerns;

use App\Support\BoxIdentity;
use Illuminate\Database\Eloquent\Builder;

trait ClaimsGlobalBoxIdentity
{
    public static function bootClaimsGlobalBoxIdentity(): void
    {
        static::updating(function ($box) {
            if ($box->isDirty(['box_number', 'identity_cycle'])) {
                throw \Illuminate\Validation\ValidationException::withMessages(['box_number' => 'Identitas box tidak dapat diganti setelah tercatat.']);
            }
            if (($box->isDirty('is_withdrawn') && ! $box->is_withdrawn)
                || ($box->isDirty('deleted_at') && $box->deleted_at === null)
                || ($box->isDirty('state') && in_array($box->state, ['received', 'stored'], true)
                    && in_array($box->getOriginal('state'), ['shipped', 'removed'], true))) {
                BoxIdentity::assertCanReactivate($box);
            }
        });
        static::restoring(fn ($box) => BoxIdentity::assertCanReactivate($box));
    }

    protected function performInsert(Builder $query)
    {
        return $this->getConnection()->transaction(function () use ($query) {
            $this->identity_cycle = BoxIdentity::claim($this, $this->adoptsLegacyIdentity());

            return parent::performInsert($query);
        });
    }

    protected function performUpdate(Builder $query)
    {
        // Reactivation checks and the update must retain the same identity lock.
        return $this->getConnection()->transaction(fn () => parent::performUpdate($query));
    }

    protected function adoptsLegacyIdentity(): bool
    {
        return false;
    }
}
