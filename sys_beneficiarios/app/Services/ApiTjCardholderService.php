<?php

namespace App\Services;

use App\Models\ApiTjSyncRun;
use App\Models\Beneficiario;
use App\Models\Tarjeta;
use App\Models\User;
use App\Support\ApiTjHelper;
use Illuminate\Support\Str;

class ApiTjCardholderService
{
    public function __construct(private readonly ApiTjClient $client)
    {
    }

    public function pushBeneficiario(Beneficiario $beneficiario, User $actor): ApiTjSyncRun
    {
        $tarjetaNumero = $beneficiario->tarjeta?->folio ?: trim((string) $beneficiario->folio_tarjeta);

        $run = ApiTjSyncRun::create([
            'id' => (string) Str::uuid(),
            'sync_id' => 'CARDHOLDER-'.now()->format('Ymd-His').'-'.Str::upper(Str::random(6)),
            'executed_by' => $actor->uuid,
            'role' => $actor->getRoleNames()->first(),
            'started_at' => now(),
            'request_count' => 1,
            'status' => ApiTjSyncRun::STATUS_RUNNING,
        ]);

        try {
            if (! ApiTjHelper::isValidCurp($beneficiario->curp) || $tarjetaNumero === '') {
                throw new \RuntimeException('Beneficiario no elegible para /api/v1/cardholders.');
            }

            $response = $this->client->createOrUpdateCardholder([
                'curp_hash' => ApiTjHelper::hashCurp($beneficiario->curp, (string) config('api_tj.curp_hash_secret', '')),
                'curp_masked' => ApiTjHelper::maskCurp($beneficiario->curp),
                'tarjeta_numero' => $tarjetaNumero,
                'status' => $this->mapStatus($beneficiario),
            ]);

            $run->fill([
                'success_count' => $response->successful() ? 1 : 0,
                'failed_count' => $response->successful() ? 0 : 1,
                'api_status_code' => $response->status(),
                'api_response_body' => $this->client->sanitizeResponse($response),
                'status' => $response->successful() ? ApiTjSyncRun::STATUS_SUCCESS : ApiTjSyncRun::STATUS_ERROR,
                'error_message' => $response->failed() ? $this->client->sanitizeResponse($response) : null,
                'finished_at' => now(),
            ])->save();
        } catch (\Throwable $exception) {
            $run->fill([
                'success_count' => 0,
                'failed_count' => 1,
                'status' => ApiTjSyncRun::STATUS_ERROR,
                'error_message' => $exception->getMessage(),
                'finished_at' => now(),
            ])->save();
        }

        return $run->fresh();
    }

    private function mapStatus(Beneficiario $beneficiario): string
    {
        if (! $beneficiario->tarjeta) {
            return 'active';
        }

        return match ($beneficiario->tarjeta->estatus) {
            Tarjeta::STATUS_BLOQUEADA, Tarjeta::STATUS_EXTRAVIADA => 'blocked',
            Tarjeta::STATUS_DEVUELTA, Tarjeta::STATUS_ASIGNADA_OFICINA, Tarjeta::STATUS_DISPONIBLE => 'inactive',
            default => 'active',
        };
    }
}
