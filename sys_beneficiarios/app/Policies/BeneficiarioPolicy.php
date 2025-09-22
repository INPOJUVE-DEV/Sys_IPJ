<?php

namespace App\Policies;

use App\Models\Beneficiario;
use App\Models\User;

class BeneficiarioPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin','capturista','encargado_360']);
    }

    public function view(User $user, Beneficiario $beneficiario): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }
        if ($user->hasRole('capturista') || $user->hasRole('encargado_360')) {
            return $beneficiario->created_by === $user->uuid;
        }
        return false;
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['admin','capturista','encargado_360']);
    }

    public function update(User $user, Beneficiario $beneficiario): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }
        if ($user->hasRole('capturista') || $user->hasRole('encargado_360')) {
            return $beneficiario->created_by === $user->uuid;
        }
        return false;
    }

    public function delete(User $user, Beneficiario $beneficiario): bool
    {
        // Soft delete permitido solo a admin
        return $user->hasAnyRole(['admin']);
    }

    public function restore(User $user, Beneficiario $beneficiario): bool
    {
        return $user->hasRole('admin');
    }

    public function forceDelete(User $user, Beneficiario $beneficiario): bool
    {
        // Delete duro solo admin
        return $user->hasRole('admin');
    }
}
