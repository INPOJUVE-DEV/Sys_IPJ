<?php

namespace App\Policies;

use App\Models\Theme;
use App\Models\User;

class ThemePolicy
{
    protected array $adminRoles = ['admin'];

    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole($this->adminRoles);
    }

    public function update(User $user, ?Theme $theme = null): bool
    {
        return $user->hasAnyRole($this->adminRoles);
    }
}