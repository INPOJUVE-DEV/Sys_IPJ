<?php

namespace App\Policies;

use App\Models\ComponentCatalog;
use App\Models\User;

class ComponentCatalogPolicy
{
    protected array $adminRoles = ['admin'];

    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole($this->adminRoles);
    }

    public function upsert(User $user): bool
    {
        return $user->hasAnyRole($this->adminRoles);
    }

    public function registry(User $user): bool
    {
        return $user->hasAnyRole($this->adminRoles);
    }
}
