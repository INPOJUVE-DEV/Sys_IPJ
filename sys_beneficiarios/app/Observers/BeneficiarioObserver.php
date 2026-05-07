<?php

namespace App\Observers;

use App\Models\Beneficiario;
use App\Support\ApiTjHelper;

class BeneficiarioObserver
{
    public function saving(Beneficiario $beneficiario): void
    {
        // Normalizamos la CURP una sola vez para mantener matching y hash consistentes.
        $beneficiario->curp = ApiTjHelper::normalizeCurp($beneficiario->curp);
        $beneficiario->email = $this->normalizeEmail($beneficiario->email);
        $beneficiario->status = $beneficiario->status ?: Beneficiario::STATUS_ACTIVE;
        $beneficiario->curp_hash = $this->resolveCurpHash($beneficiario->curp);

        // Solo recalculamos el estado de sync cuando cambian datos de negocio.
        if ($this->shouldRefreshSyncStatus($beneficiario)) {
            $beneficiario->api_tj_sync_status = $beneficiario->hasCompleteApiTjProfile()
                ? Beneficiario::API_TJ_SYNC_STATUS_PENDING_SYNC
                : Beneficiario::API_TJ_SYNC_STATUS_PENDING_DATA;
        }
    }

    private function normalizeEmail(?string $email): ?string
    {
        $email = trim((string) $email);

        return $email !== '' ? mb_strtolower($email) : null;
    }

    private function resolveCurpHash(?string $curp): ?string
    {
        $secret = trim((string) config('api_tj.curp_hash_secret', ''));
        if ($secret === '' || ! ApiTjHelper::isValidCurp($curp)) {
            return null;
        }

        return ApiTjHelper::hashCurp($curp, $secret);
    }

    private function shouldRefreshSyncStatus(Beneficiario $beneficiario): bool
    {
        if (! $beneficiario->exists) {
            return true;
        }

        foreach (Beneficiario::apiTjSyncRelevantAttributes() as $attribute) {
            if ($beneficiario->isDirty($attribute)) {
                return true;
            }
        }

        return false;
    }
}
