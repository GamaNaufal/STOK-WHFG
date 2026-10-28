<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

trait HasMasterScope
{
    protected static function bootHasMasterScope(): void
    {
        $scope = static::MASTER_SCOPE;
        static::addGlobalScope('master_scope', function (Builder $query) use ($scope) {
            $query->where($query->getModel()->qualifyColumn('scope'), $scope);
        });

        static::saving(function ($model) use ($scope) {
            // Domain is fixed by the model, never by a request or authenticated role.
            $model->setAttribute('scope', $scope);
        });
    }
}
