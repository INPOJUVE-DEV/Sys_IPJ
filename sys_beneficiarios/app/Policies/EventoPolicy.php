<?php

namespace App\Policies;

use App\Models\Evento;
use App\Models\User;

class EventoPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'delegado']);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'delegado']);
    }

    public function update(User $user, Evento $evento): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        return $user->hasRole('delegado') && $evento->created_by === $user->uuid;
    }

    public function delete(User $user, Evento $evento): bool
    {
        return $user->hasRole('admin');
    }
}
