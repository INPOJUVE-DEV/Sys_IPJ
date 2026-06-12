<?php

namespace App\Services\Integrations\ApiTj;

use App\Models\Integrations\IntegrationClient;
use App\Models\Integrations\IntegrationClientKey;
use App\Models\Integrations\IntegrationInboundRequest;
use App\Models\Integrations\IntegrationSyncRun;
use App\Models\User;

class ApiTjOperationalStatusService
{
    /**
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        $integrationUserEmail = trim((string) config('integrations.api_tj.integration_user_email'));
        $apiTjClient = IntegrationClient::query()
            ->where('client_code', 'api_tj')
            ->where('status', IntegrationClient::STATUS_ACTIVE)
            ->first();

        $hasActiveInboundKey = false;
        if ($apiTjClient) {
            $hasActiveInboundKey = IntegrationClientKey::query()
                ->where('client_id', $apiTjClient->id)
                ->where('status', IntegrationClientKey::STATUS_ACTIVE)
                ->exists();
        }

        $outboundBaseUrl = trim((string) config('integrations.outbound.base_url'));
        $privateKeyPath = trim((string) config('integrations.outbound.private_key_path'));
        $payloadKey = trim((string) config('integrations.payload_encryption_key'));

        return [
            'checks' => [
                [
                    'label' => 'Cliente inbound API_TJ activo',
                    'ready' => (bool) $apiTjClient,
                    'detail' => $apiTjClient
                        ? 'Cliente registrado en integration_clients.'
                        : 'Falta cliente api_tj activo en integration_clients.',
                ],
                [
                    'label' => 'Llave inbound activa',
                    'ready' => $hasActiveInboundKey,
                    'detail' => $hasActiveInboundKey
                        ? 'Existe al menos una llave activa para validar JWT inbound.'
                        : 'No existe una llave activa para api_tj.',
                ],
                [
                    'label' => 'Usuario tecnico disponible',
                    'ready' => $integrationUserEmail !== '' && User::query()->where('email', $integrationUserEmail)->exists(),
                    'detail' => $integrationUserEmail !== ''
                        ? "Configurado como {$integrationUserEmail}."
                        : 'No existe API_TJ_INTEGRATION_USER_EMAIL configurado.',
                ],
                [
                    'label' => 'Llave de cifrado inbound',
                    'ready' => $payloadKey !== '',
                    'detail' => $payloadKey !== ''
                        ? 'INTEGRATION_PAYLOAD_ENCRYPTION_KEY presente.'
                        : 'Falta INTEGRATION_PAYLOAD_ENCRYPTION_KEY.',
                ],
                [
                    'label' => 'Destino outbound configurado',
                    'ready' => $outboundBaseUrl !== '',
                    'detail' => $outboundBaseUrl !== ''
                        ? $outboundBaseUrl
                        : 'Falta API_TJ_BASE_URL.',
                ],
                [
                    'label' => 'Llave privada outbound',
                    'ready' => $privateKeyPath !== '' && is_file($privateKeyPath),
                    'detail' => $privateKeyPath !== ''
                        ? $privateKeyPath
                        : 'Falta SYS_IPJ_PRIVATE_KEY_PATH.',
                ],
            ],
            'counters' => [
                'sync_runs_total' => IntegrationSyncRun::query()->count(),
                'sync_runs_failed' => IntegrationSyncRun::query()->where('status', IntegrationSyncRun::STATUS_FAILED)->count(),
                'sync_runs_partial' => IntegrationSyncRun::query()->where('status', IntegrationSyncRun::STATUS_PARTIAL)->count(),
                'inbound_total' => IntegrationInboundRequest::query()->count(),
                'inbound_failed' => IntegrationInboundRequest::query()->where('status', IntegrationInboundRequest::STATUS_FAILED)->count(),
                'inbound_rejected' => IntegrationInboundRequest::query()->where('status', IntegrationInboundRequest::STATUS_REJECTED)->count(),
            ],
            'latest' => [
                'run' => IntegrationSyncRun::query()->latest('created_at')->first(),
                'inbound' => IntegrationInboundRequest::query()->latest('received_at')->first(),
            ],
        ];
    }
}
