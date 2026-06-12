<?php

namespace App\Services\Integrations\ApiTj;

use Illuminate\Http\JsonResponse;

class ApiTjStagingAcceptResult
{
    /**
     * @param  array<string, mixed>  $body
     */
    public function __construct(
        public readonly int $statusCode,
        public readonly array $body,
    ) {
    }

    public static function created(string $externalRequestId, string $beneficiarioId): self
    {
        return new self(201, [
            'accepted' => true,
            'status' => 'created',
            'external_request_id' => $externalRequestId,
            'beneficiario_id' => $beneficiarioId,
            'message' => 'Beneficiario creado correctamente',
        ]);
    }

    public static function alreadyProcessed(string $externalRequestId, ?string $beneficiarioId = null): self
    {
        return new self(200, array_filter([
            'accepted' => true,
            'status' => 'already_processed',
            'external_request_id' => $externalRequestId,
            'beneficiario_id' => $beneficiarioId,
            'message' => 'Solicitud ya procesada previamente',
        ], static fn ($value) => ! is_null($value)));
    }

    public static function duplicate(string $externalRequestId): self
    {
        return new self(409, [
            'accepted' => false,
            'status' => 'duplicate',
            'external_request_id' => $externalRequestId,
            'message' => 'Ya existe un beneficiario con la CURP proporcionada',
        ]);
    }

    /**
     * @param  array<string, mixed>  $errors
     */
    public static function validationError(string $externalRequestId, array $errors): self
    {
        return new self(422, [
            'accepted' => false,
            'status' => 'validation_error',
            'external_request_id' => $externalRequestId,
            'errors' => $errors,
        ]);
    }

    public static function conflict(string $externalRequestId, string $message = 'La solicitud se encuentra en procesamiento.'): self
    {
        return new self(409, [
            'accepted' => false,
            'status' => 'conflict',
            'external_request_id' => $externalRequestId,
            'message' => $message,
        ]);
    }

    public static function serverError(string $externalRequestId, string $message): self
    {
        return new self(500, [
            'accepted' => false,
            'status' => 'error',
            'external_request_id' => $externalRequestId,
            'message' => $message,
        ]);
    }

    public function toResponse(): JsonResponse
    {
        return response()->json($this->body, $this->statusCode);
    }
}
