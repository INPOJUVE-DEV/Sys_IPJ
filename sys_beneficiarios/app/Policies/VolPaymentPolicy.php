<?php

namespace App\Policies;

use App\Models\User;
use App\Models\VolPayment;

class VolPaymentPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->canManage($user);
    }

    public function view(User $user, VolPayment $volPayment): bool
    {
        return $this->canManage($user);
    }

    public function create(User $user): bool
    {
        return $this->canManage($user);
    }

    public function update(User $user, VolPayment $volPayment): bool
    {
        return $this->canManage($user);
    }

    public function delete(User $user, VolPayment $volPayment): bool
    {
        return $this->canManage($user);
    }

    public function restore(User $user, VolPayment $volPayment): bool
    {
        return $user->hasRole('admin');
    }

    public function forceDelete(User $user, VolPayment $volPayment): bool
    {
        return $user->hasRole('admin');
    }

    private function canManage(User $user): bool
    {
        return $user->hasRole('admin') || $user->can('vol.groups.manage');
    }
}