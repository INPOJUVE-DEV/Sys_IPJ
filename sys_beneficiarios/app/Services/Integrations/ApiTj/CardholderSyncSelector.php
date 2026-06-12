<?php

namespace App\Services\Integrations\ApiTj;

use App\Models\Beneficiario;
use Illuminate\Database\Eloquent\Builder;

class CardholderSyncSelector
{
    public function queryEligible(): Builder
    {
        return Beneficiario::query()
            ->with([
                'tarjeta' => fn ($query) => $query->select(['id', 'folio', 'estatus', 'beneficiario_id']),
            ])
            ->whereNotNull('curp')
            ->where('curp', '!=', '')
            ->orderBy('created_at')
            ->orderBy('id');
    }
}
