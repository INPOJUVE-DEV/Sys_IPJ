<?php

namespace App\Policies;

use App\Models\Page;
use App\Models\User;

class PagePolicy
{
    protected array $allowedRoles = ['admin'];

    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole($this->allowedRoles);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole($this->allowedRoles);
    }

    public function update(User $user, Page $page): bool
    {
        return $user->hasAnyRole($this->allowedRoles);
    }

    public function publish(User $user, Page $page): bool
    {
        return $user->hasAnyRole($this->allowedRoles);
    }

    public function viewVersions(User $user, Page $page): bool
    {
        return $user->hasAnyRole($this->allowedRoles);
    }

    public function rollback(User $user, Page $page): bool
    {
        return $user->hasAnyRole($this->allowedRoles);
    }
}
