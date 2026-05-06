<?php

namespace App\Services;

use App\Models\ApiTjSyncRun;
use App\Models\Beneficiario;
use App\Models\Tarjeta;
use App\Models\User;
use App\Support\ApiTjHelper;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class ApiTjSyncService
{
    public function __construct(private readonly ApiTjJwtService $jwtService)
    {
    }

    public function sync(User $actor): ApiTjSyncRun
    {
        $items = $this->buildItems();
        $run = ApiTjSyncRun::create([
            'id' => (string) Str::uuid(),
            'sync_id' => 'SYSIPJ-'.now()->format('Ymd-His').'-'.Str::upper(Str::random(6)),
            'executed_by' => $actor->uuid,
            'role' => $actor->getRoleNames()->first(),
            'started_at' => now(),
            'request_count' => $items->count(),
            'status' => ApiTjSyncRun::STATUS_RUNNING,
        ]);

        if ($items->isEmpty()) {
            $run->fill([
                'success_count' => 0,
                'failed_count' => 0,
                'api_response_body' => json_encode(['message' => 'No hay beneficiarios elegibles para sincronizar.'], JSON_UNESCAPED_UNICODE),
                'status' => ApiTjSyncRun::STATUS_SUCCESS,
                'finished_at' => now(),
            ])->save();

            return $run->fresh();
        }

        try {
            if (trim((string) config('services.sys_ipj.curp_hash_secret', '')) === '') {
                throw new \RuntimeException('CURP_HASH_SECRET no esta configurado.');
            }

            $token = $this->jwtService->sign([
                'iss' => config('services.sys_ipj.client_code', 'sys_ipj'),
                'sub' => config('services.sys_ipj.client_code', 'sys_ipj'),
                'aud' => config('services.sys_ipj.audience', 'api_tj'),
                'scope' => config('services.sys_ipj.scope', 'cardholders.sync'),
                'jti' => (string) Str::uuid(),
                'iat' => now()->timestamp,
                'exp' => now()->addMinutes(10)->timestamp,
            ], [
                'alg' => 'RS256',
                'typ' => 'JWT',
                'kid' => config('services.sys_ipj.jwt_kid', 'sys_ipj-current'),
            ], config('services.sys_ipj.private_key_path'));

            $payload = [
                'sync_id' => $run->sync_id,
                'items' => $items->values()->all(),
            ];

            $response = Http::timeout(config('services.api_tj.timeout', 15))
                ->withToken($token)
                ->acceptJson()
                ->post(rtrim(config('services.api_tj.base_url'), '/').'/api/v1/cardholders/sync', $payload);

            $json = $response->json();
            $successCount = (int) ($json['success_count'] ?? ($response->successful() ? $items->count() : 0));
            $failedCount = (int) ($json['failed_count'] ?? max(0, $items->count() - $successCount));
            $status = $response->successful()
                ? ($failedCount > 0 || ($json['status'] ?? null) === 'partial' ? ApiTjSyncRun::STATUS_PARTIAL : ApiTjSyncRun::STATUS_SUCCESS)
                : ApiTjSyncRun::STATUS_ERROR;

            $run->fill([
                'success_count' => $successCount,
                'failed_count' => $failedCount,
                'api_status_code' => $response->status(),
                'api_response_body' => ApiTjHelper::sanitizeResponseBody($json ?: $response->body()),
                'status' => $status,
                'error_message' => $response->failed() ? ApiTjHelper::sanitizeResponseBody($json ?: $response->body()) : null,
                'finished_at' => now(),
            ])->save();
        } catch (\Throwable $exception) {
            $run->fill([
                'failed_count' => $items->count(),
                'status' => ApiTjSyncRun::STATUS_ERROR,
                'error_message' => $exception->getMessage(),
                'finished_at' => now(),
            ])->save();
        }

        return $run->fresh();
    }

    public function buildItems(): Collection
    {
        $secret = (string) config('services.sys_ipj.curp_hash_secret', '');

        return Beneficiario::query()
            ->with(['tarjeta'])
            ->whereNull('deleted_at')
            ->whereNotNull('curp')
            ->where(function ($query) {
                $query->whereNotNull('tarjeta_id')
                    ->orWhereNotNull('folio_tarjeta');
            })
            ->get()
            ->map(function (Beneficiario $beneficiario) use ($secret) {
                if (! ApiTjHelper::isValidCurp($beneficiario->curp)) {
                    return null;
                }

                $tarjetaNumero = $beneficiario->tarjeta?->folio ?: trim((string) $beneficiario->folio_tarjeta);
                if ($tarjetaNumero === '') {
                    return null;
                }

                return [
                    'curp_hash' => ApiTjHelper::hashCurp($beneficiario->curp, $secret),
                    'curp_masked' => ApiTjHelper::maskCurp($beneficiario->curp),
                    'tarjeta_numero' => $tarjetaNumero,
                    'status' => $this->mapStatus($beneficiario),
                ];
            })
            ->filter()
            ->values();
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
