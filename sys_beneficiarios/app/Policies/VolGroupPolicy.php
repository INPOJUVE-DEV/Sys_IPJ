<?php

namespace App\Policies;

use App\Models\User;
use App\Models\VolGroup;

class VolGroupPolicy
{
    private const MANAGE_ROLES = ['admin', 'encargadoBienestar', 'encargado_bienestar'];
    private const VIEW_EXTRA_ROLES = ['encargado_JAV', 'encargado_jav'];

    public function viewAny(User $user): bool
    {
        return $this->canView($user);
    }

    public function view(User $user, VolGroup $volGroup): bool
    {
        return $this->canView($user);
    }

    public function create(User $user): bool
    {
        return $this->canManage($user);
    }

    public function update(User $user, VolGroup $volGroup): bool
    {
        return $this->canManage($user);
    }

    public function delete(User $user, VolGroup $volGroup): bool
    {
        return $this->canManage($user);
    }

    public function restore(User $user, VolGroup $volGroup): bool
    {
        return $user->hasRole('admin');
    }

    public function forceDelete(User $user, VolGroup $volGroup): bool
    {
        return $user->hasRole('admin');
    }

    private function canManage(User $user): bool
    {
        return $user->hasAnyRole(self::MANAGE_ROLES);
    }

    private function canView(User $user): bool
    {
        return $user->hasAnyRole(array_merge(self::MANAGE_ROLES, self::VIEW_EXTRA_ROLES));
    }
}