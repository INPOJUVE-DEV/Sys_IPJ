<?php

namespace App\Policies;

use App\Models\User;
use App\Models\VolSite;

class VolSitePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->canManage($user);
    }

    public function view(User $user, VolSite $volSite): bool
    {
        return $this->canManage($user);
    }

    public function create(User $user): bool
    {
        return $this->canManage($user);
    }

    public function update(User $user, VolSite $volSite): bool
    {
        return $this->canManage($user);
    }

    public function delete(User $user, VolSite $volSite): bool
    {
        return $this->canManage($user);
    }

    public function restore(User $user, VolSite $volSite): bool
    {
        return $user->hasRole('admin');
    }

    public function forceDelete(User $user, VolSite $volSite): bool
    {
        return $user->hasRole('admin');
    }

    private function canManage(User $user): bool
    {
        return $user->hasRole('admin') || $user->can('vol.sites.manage');
    }
}