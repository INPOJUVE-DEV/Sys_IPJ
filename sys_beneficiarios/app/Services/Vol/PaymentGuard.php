<?php

namespace App\Services\Vol;

use App\Models\VolPayment;

class PaymentGuard
{
    public function hasValidPayment(string|int $beneficiarioId): bool
    {
        return VolPayment::query()
            ->where('beneficiario_id', $beneficiarioId)
            ->whereNotNull('payment_date')
            ->exists();
    }
}
