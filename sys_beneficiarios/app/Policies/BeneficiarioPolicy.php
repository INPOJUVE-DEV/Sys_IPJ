<?php

namespace App\Policies;

use App\Models\Beneficiario;
use App\Models\User;

class BeneficiarioPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'delegado', 'capturista']);
    }

    public function view(User $user, Beneficiario $beneficiario): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }
        if ($user->hasRole('capturista')) {
            return $beneficiario->created_by === $user->uuid;
        }
        if ($user->hasRole('delegado')) {
            return $this->belongsToUserOffice($user, $beneficiario);
        }
        return false;
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'delegado', 'capturista']);
    }

    public function update(User $user, Beneficiario $beneficiario): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }
        if ($user->hasRole('capturista')) {
            return $beneficiario->created_by === $user->uuid;
        }
        if ($user->hasRole('delegado')) {
            return $this->belongsToUserOffice($user, $beneficiario);
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

    protected function belongsToUserOffice(User $user, Beneficiario $beneficiario): bool
    {
        if (! $user->oficina_id) {
            return false;
        }

        $officeId = $beneficiario->tarjeta?->oficina_id
            ?? $beneficiario->municipio?->oficina_id
            ?? $beneficiario->domicilio?->municipio?->oficina_id
            ?? $beneficiario->creador?->oficina_id;

        return $officeId !== null && (int) $officeId === (int) $user->oficina_id;
    }
}
