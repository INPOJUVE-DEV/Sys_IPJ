<?php

namespace App\Policies;

use App\Models\User;
use App\Models\VolEnrollment;

class VolEnrollmentPolicy
{
    private const MANAGE_ROLES = ['admin', 'encargado_JAV', 'encargado_jav'];
    private const VIEW_EXTRA_ROLES = ['encargadoBienestar', 'encargado_bienestar'];

    public function viewAny(User $user): bool
    {
        return $this->canView($user);
    }

    public function view(User $user, VolEnrollment $volEnrollment): bool
    {
        return $this->canView($user);
    }

    public function create(User $user): bool
    {
        return $this->canManage($user);
    }

    public function update(User $user, VolEnrollment $volEnrollment): bool
    {
        return $this->canManage($user);
    }

    public function delete(User $user, VolEnrollment $volEnrollment): bool
    {
        return $this->canManage($user);
    }

    public function restore(User $user, VolEnrollment $volEnrollment): bool
    {
        return $this->canManage($user);
    }

    public function forceDelete(User $user, VolEnrollment $volEnrollment): bool
    {
        return $this->canManage($user);
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