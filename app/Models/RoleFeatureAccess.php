<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RoleFeatureAccess extends Model
{
    protected $table = 'role_feature_access';

    protected $fillable = ['role', 'permissions', 'permissions_version'];

    protected $casts = ['permissions' => 'array', 'permissions_version' => 'integer'];
}
