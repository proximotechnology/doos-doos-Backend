<?php

namespace App\Models\Traits;

use Spatie\Permission\Traits\HasRoles as SpatieHasRoles;

trait HasConditionalRoles
{
    use SpatieHasRoles;

    public function hasRole($roles, $guard = null)
    {
        if ($this->type != 1) {
            return false;
        }
        return parent::hasRole($roles, $guard);
    }

    public function assignRole(...$roles)
    {
        if ($this->type != 1) {
            return;
        }
        parent::assignRole(...$roles);
    }
}
