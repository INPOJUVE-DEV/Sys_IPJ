<?php

namespace App\Services\Vol;

use App\Models\VolGroup;

class CapacityGuard
{
    public function hasCapacity(VolGroup $group): bool
    {
        $activeCount = $group->enrollments()
            ->where('status', 'inscrito')
            ->count();

        return $activeCount < $group->capacity;
    }
}
