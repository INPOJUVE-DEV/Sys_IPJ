<?php

namespace App\Services\Integrations\Inbound;

use App\Models\Integrations\IntegrationInboundRequest;
use Illuminate\Support\Str;

class InboundIdempotencyService
{
    public function __construct(
        private readonly InboundPayloadEncrypter $payloadEncrypter,
    ) {
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function resolveOrCreate(
        string $sourceSystem,
        string $externalRequestId,
        array $payload,
        string $operation = 'beneficiarios.staging.accept',
    ): IntegrationInboundRequest {
        $requestHash = $this->hashPayload($payload);
        $encryptedPayload = $this->payloadEncrypter->encrypt($payload);

        $request = IntegrationInboundRequest::query()
            ->where('source_system', $sourceSystem)
            ->where('external_request_id', $externalRequestId)
            ->lockForUpdate()
            ->first();

        if (! $request) {
            return IntegrationInboundRequest::query()->create([
                'id' => (string) Str::uuid(),
                'source_system' => $sourceSystem,
                'external_request_id' => $externalRequestId,
                'operation' => $operation,
                'request_hash' => $requestHash,
                'request_payload_encrypted' => $encryptedPayload,
                'status' => IntegrationInboundRequest::STATUS_RECEIVED,
                'received_at' => now(),
            ]);
        }

        if ($request->request_hash !== $requestHash) {
            throw new InboundRequestConflictException('El external_request_id ya existe con un payload distinto.');
        }

        if (in_array($request->status, [
            IntegrationInboundRequest::STATUS_FAILED,
            IntegrationInboundRequest::STATUS_REJECTED,
            IntegrationInboundRequest::STATUS_RECEIVED,
        ], true)) {
            $request->forceFill([
                'operation' => $operation,
                'request_hash' => $requestHash,
                'request_payload_encrypted' => $encryptedPayload,
                'status' => IntegrationInboundRequest::STATUS_RECEIVED,
                'response_code' => null,
                'response_body' => null,
                'error_message' => null,
                'processed_at' => null,
            ])->save();
        }

        return $request->fresh();
    }

    public function markProcessing(IntegrationInboundRequest $request): IntegrationInboundRequest
    {
        $request->forceFill([
            'status' => IntegrationInboundRequest::STATUS_PROCESSING,
            'response_code' => null,
            'response_body' => null,
            'error_message' => null,
            'processed_at' => null,
        ])->save();

        return $request->fresh();
    }

    /**
     * @param  array<string, mixed>  $responseBody
     */
    public function markAccepted(IntegrationInboundRequest $request, int $responseCode, array $responseBody): IntegrationInboundRequest
    {
        $request->forceFill([
            'status' => IntegrationInboundRequest::STATUS_ACCEPTED,
            'response_code' => $responseCode,
            'response_body' => $responseBody,
            'error_message' => null,
            'processed_at' => now(),
        ])->save();

        return $request->fresh();
    }

    /**
     * @param  array<string, mixed>  $responseBody
     */
    public function markRejected(
        IntegrationInboundRequest $request,
        int $responseCode,
        array $responseBody,
        ?string $errorMessage = null,
    ): IntegrationInboundRequest {
        $request->forceFill([
            'status' => IntegrationInboundRequest::STATUS_REJECTED,
            'response_code' => $responseCode,
            'response_body' => $responseBody,
            'error_message' => $errorMessage,
            'processed_at' => now(),
        ])->save();

        return $request->fresh();
    }

    /**
     * @param  array<string, mixed>  $responseBody
     */
    public function markFailed(
        IntegrationInboundRequest $request,
        int $responseCode,
        array $responseBody,
        string $errorMessage,
    ): IntegrationInboundRequest {
        $request->forceFill([
            'status' => IntegrationInboundRequest::STATUS_FAILED,
            'response_code' => $responseCode,
            'response_body' => $responseBody,
            'error_message' => $errorMessage,
            'processed_at' => now(),
        ])->save();

        return $request->fresh();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function hashPayload(array $payload): string
    {
        return hash(
            'sha256',
            json_encode($this->sortRecursively($payload), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function sortRecursively(array $payload): array
    {
        foreach ($payload as $key => $value) {
            if (is_array($value)) {
                $payload[$key] = $this->sortRecursively($value);
            }
        }

        ksort($payload);

        return $payload;
    }
}
