<?php

namespace App\Services;

use App\Models\ApiTjSyncRun;
use App\Models\Beneficiario;
use App\Models\Tarjeta;
use App\Models\User;
use App\Support\ApiTjHelper;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ApiTjSyncService
{
    public function __construct(
        private readonly ApiTjClient $client,
    )
    {
    }

    public function sync(User $actor): ApiTjSyncRun
    {
        $beneficiarios = $this->eligibleBeneficiarios();
        $run = ApiTjSyncRun::create([
            'id' => (string) Str::uuid(),
            'sync_id' => 'SYSIPJ-'.now()->format('Ymd-His').'-'.Str::upper(Str::random(6)),
            'executed_by' => $actor->uuid,
            'role' => $actor->getRoleNames()->first(),
            'started_at' => now(),
            'request_count' => $beneficiarios->count(),
            'status' => ApiTjSyncRun::STATUS_RUNNING,
        ]);

        $payload = [
            'sync_id' => $run->sync_id,
            'items' => $this->buildItems($beneficiarios)->values()->all(),
        ];

        $run->forceFill(['request_payload_json' => $payload])->save();

        if ($beneficiarios->isEmpty()) {
            $run->fill([
                'success_count' => 0,
                'failed_count' => 0,
                'api_response_body' => json_encode(['message' => 'No hay beneficiarios pendientes de sincronizar.'], JSON_UNESCAPED_UNICODE),
                'status' => ApiTjSyncRun::STATUS_SUCCESS,
                'finished_at' => now(),
            ])->save();

            return $run->fresh();
        }

        try {
            $response = $this->sendPayload($payload);

            if ($response->failed()) {
                $this->markManyAsFailed($beneficiarios, $this->client->sanitizeResponse($response) ?? 'Error de API_TJ.');

                $run->fill([
                    'success_count' => 0,
                    'failed_count' => $beneficiarios->count(),
                    'api_status_code' => $response->status(),
                    'api_response_body' => $this->client->sanitizeResponse($response),
                    'status' => ApiTjSyncRun::STATUS_FAILED,
                    'error_message' => $this->client->sanitizeResponse($response),
                    'finished_at' => now(),
                ])->save();

                return $run->fresh();
            }

            $stats = $this->applySuccessfulResponse($beneficiarios, $response);
            $run->fill([
                'success_count' => $stats['success_count'],
                'failed_count' => $stats['failed_count'],
                'api_status_code' => $response->status(),
                'api_response_body' => $this->client->sanitizeResponse($response),
                'status' => $stats['failed_count'] === 0 ? ApiTjSyncRun::STATUS_SUCCESS : ApiTjSyncRun::STATUS_FAILED,
                'error_message' => $stats['failed_count'] === 0 ? null : 'La respuesta de API_TJ reporto errores parciales.',
                'finished_at' => now(),
            ])->save();
        } catch (\Throwable $exception) {
            $this->markManyAsFailed($beneficiarios, $exception->getMessage());

            $run->fill([
                'success_count' => 0,
                'failed_count' => $beneficiarios->count(),
                'status' => ApiTjSyncRun::STATUS_FAILED,
                'error_message' => $exception->getMessage(),
                'finished_at' => now(),
            ])->save();
        }

        return $run->fresh();
    }

    public function buildItems(?Collection $beneficiarios = null): Collection
    {
        $beneficiarios ??= $this->eligibleBeneficiarios();

        return $beneficiarios->map(function (Beneficiario $beneficiario) {
            return [
                'curp_hash' => $beneficiario->curp_hash,
                'curp_masked' => ApiTjHelper::maskCurp($beneficiario->curp),
                'tarjeta_numero' => $beneficiario->apiTjTarjetaNumero(),
                'status' => $this->mapStatus($beneficiario),
            ];
        });
    }

    private function eligibleBeneficiarios(): Collection
    {
        return Beneficiario::query()
            ->whereNull('deleted_at')
            ->where('status', Beneficiario::STATUS_ACTIVE)
            ->whereIn('api_tj_sync_status', [
                Beneficiario::API_TJ_SYNC_STATUS_PENDING_SYNC,
                Beneficiario::API_TJ_SYNC_STATUS_SYNC_FAILED,
            ])
            ->orderBy('created_at')
            ->get()
            ->filter(fn (Beneficiario $beneficiario) => $beneficiario->hasCompleteApiTjProfile())
            ->values();
    }

    private function sendPayload(array $payload): Response
    {
        // Centralizamos la salida para que UI, API y consola usen el mismo flujo.
        return $this->client->syncBeneficiarios($payload);
    }

    private function applySuccessfulResponse(Collection $beneficiarios, Response $response): array
    {
        $json = $response->json();
        $results = collect(is_array($json['results'] ?? null) ? $json['results'] : []);
        if ($results->isEmpty()) {
            $beneficiarios->each(fn (Beneficiario $beneficiario) => $this->markAsSynced($beneficiario));

            return [
                'success_count' => $beneficiarios->count(),
                'failed_count' => 0,
            ];
        }

        $successCount = 0;
        $failedCount = 0;

        foreach ($beneficiarios as $index => $beneficiario) {
            $result = $this->findResultForBeneficiario($results, $beneficiario, $index);

            if ($this->isSuccessfulItemResult($result)) {
                $this->markAsSynced($beneficiario);
                $successCount++;
                continue;
            }

            $this->markAsFailed($beneficiario, $this->resolveResultMessage($result));
            $failedCount++;
        }

        return [
            'success_count' => $successCount,
            'failed_count' => $failedCount,
        ];
    }

    private function findResultForBeneficiario(Collection $results, Beneficiario $beneficiario, int $index): mixed
    {
        $matched = $results->first(function ($result) use ($beneficiario) {
            return is_array($result)
                && (($result['curp_hash'] ?? null) === $beneficiario->curp_hash);
        });

        if ($matched !== null) {
            return $matched;
        }

        return $results->get($index);
    }

    private function isSuccessfulItemResult(mixed $result): bool
    {
        if (! is_array($result)) {
            return false;
        }

        return in_array(($result['status'] ?? null), ['success', 'synced', 'accepted', 'created', 'updated'], true);
    }

    private function resolveResultMessage(mixed $result): string
    {
        if (! is_array($result)) {
            return 'La API no devolvio detalle del error para este beneficiario.';
        }

        return ApiTjHelper::sanitizeResponseBody($result['message'] ?? $result['error'] ?? $result) ?: 'La API rechazo el beneficiario.';
    }

    private function markAsSynced(Beneficiario $beneficiario): void
    {
        $beneficiario->forceFill([
            'api_tj_sync_status' => Beneficiario::API_TJ_SYNC_STATUS_SYNCED,
            'api_tj_sync_attempts' => 0,
            'api_tj_last_sync_error' => null,
            'api_tj_last_synced_at' => now(),
        ])->saveQuietly();
    }

    private function markAsFailed(Beneficiario $beneficiario, string $message): void
    {
        $beneficiario->forceFill([
            'api_tj_sync_status' => Beneficiario::API_TJ_SYNC_STATUS_SYNC_FAILED,
            'api_tj_sync_attempts' => ((int) $beneficiario->api_tj_sync_attempts) + 1,
            'api_tj_last_sync_error' => $message,
        ])->saveQuietly();
    }

    private function markManyAsFailed(Collection $beneficiarios, string $message): void
    {
        $beneficiarios->each(fn (Beneficiario $beneficiario) => $this->markAsFailed($beneficiario, $message));
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
