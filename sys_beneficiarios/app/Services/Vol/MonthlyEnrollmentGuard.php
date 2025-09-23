<?php

namespace App\Services\Vol;

use App\Models\VolEnrollment;
use Carbon\CarbonInterface;

class MonthlyEnrollmentGuard
{
    public function allows(string|int $beneficiarioId, CarbonInterface $enrolledAt): bool
    {
        $start = $enrolledAt->copy()->startOfMonth();
        $end = $enrolledAt->copy()->endOfMonth();

        return ! VolEnrollment::query()
            ->where('beneficiario_id', $beneficiarioId)
            ->whereBetween('enrolled_at', [$start, $end])
            ->exists();
    }
}
