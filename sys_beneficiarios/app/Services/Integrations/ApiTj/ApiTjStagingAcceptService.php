<?php

namespace App\Services\Integrations\ApiTj;

use App\Models\Beneficiario;
use App\Models\Integrations\IntegrationInboundRequest;
use App\Services\Beneficiarios\BeneficiarioRegistrationService;
use App\Services\Integrations\Inbound\InboundIdempotencyService;
use App\Services\Integrations\Security\IntegrationAuthContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class ApiTjStagingAcceptService
{
    private const SOURCE_SYSTEM = 'api_tj';

    private const OPERATION = 'beneficiarios.staging.accept';

    public function __construct(
        private readonly InboundIdempotencyService $idempotencyService,
        private readonly ApiTjStagingPayloadValidator $payloadValidator,
        private readonly ApiTjTechnicalUserResolver $technicalUserResolver,
        private readonly BeneficiarioRegistrationService $registrationService,
    ) {
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function accept(array $payload, IntegrationAuthContext $auth): ApiTjStagingAcceptResult
    {
        $externalRequestId = trim((string) ($payload['external_request_id'] ?? ''));

        try {
            $validatedPayload = $this->payloadValidator->validate($payload);
        } catch (ValidationException $exception) {
            return ApiTjStagingAcceptResult::validationError($externalRequestId, $exception->errors());
        }

        $externalRequestId = (string) $validatedPayload['external_request_id'];

        if ($auth->issuer !== self::SOURCE_SYSTEM || ($validatedPayload['source'] ?? null) !== self::SOURCE_SYSTEM) {
            return ApiTjStagingAcceptResult::validationError($externalRequestId, [
                'source' => ['La solicitud no corresponde al origen esperado.'],
            ]);
        }

        try {
            return DB::transaction(function () use ($auth, $validatedPayload, $externalRequestId) {
                $request = $this->idempotencyService->resolveOrCreate(
                    $auth->issuer,
                    $externalRequestId,
                    $validatedPayload,
                    self::OPERATION,
                );

                if ($request->status === IntegrationInboundRequest::STATUS_ACCEPTED) {
                    return $this->buildAlreadyProcessedResult($request);
                }

                if ($request->status === IntegrationInboundRequest::STATUS_PROCESSING) {
                    return ApiTjStagingAcceptResult::conflict($externalRequestId);
                }

                $request = $this->idempotencyService->markProcessing($request);

                $curp = (string) data_get($validatedPayload, 'beneficiario.curp');
                if (Beneficiario::withTrashed()->where('curp', $curp)->exists()) {
                    $result = ApiTjStagingAcceptResult::duplicate($externalRequestId);
                    $this->idempotencyService->markRejected(
                        $request,
                        $result->statusCode,
                        $result->body,
                        $result->body['message'] ?? 'Duplicate CURP.',
                    );

                    return $result;
                }

                $technicalUser = $this->technicalUserResolver->resolve();
                $beneficiario = $this->registrationService->createFromIntegration($validatedPayload, $technicalUser, $request);

                $result = ApiTjStagingAcceptResult::created($externalRequestId, $beneficiario->id);
                $this->idempotencyService->markAccepted($request, $result->statusCode, $result->body);

                return $result;
            });
        } catch (ValidationException $exception) {
            return $this->rejectForValidation($auth->issuer, $externalRequestId, $validatedPayload, $exception);
        } catch (RuntimeException $exception) {
            return $this->failRequest($auth->issuer, $externalRequestId, $validatedPayload, $exception->getMessage());
        } catch (Throwable $exception) {
            report($exception);

            return $this->failRequest(
                $auth->issuer,
                $externalRequestId,
                $validatedPayload,
                'No se pudo procesar la solicitud de integracion.',
            );
        }
    }

    private function buildAlreadyProcessedResult(IntegrationInboundRequest $request): ApiTjStagingAcceptResult
    {
        $beneficiarioId = null;
        if (is_array($request->response_body)) {
            $beneficiarioId = $request->response_body['beneficiario_id'] ?? null;
        }

        return ApiTjStagingAcceptResult::alreadyProcessed(
            $request->external_request_id,
            is_string($beneficiarioId) ? $beneficiarioId : null,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function rejectForValidation(
        string $sourceSystem,
        string $externalRequestId,
        array $payload,
        ValidationException $exception,
    ): ApiTjStagingAcceptResult {
        $result = ApiTjStagingAcceptResult::validationError($externalRequestId, $exception->errors());

        $this->updateRequestSafely(function () use ($sourceSystem, $externalRequestId, $payload, $result) {
            $request = $this->idempotencyService->resolveOrCreate($sourceSystem, $externalRequestId, $payload, self::OPERATION);

            $this->idempotencyService->markRejected(
                $request,
                $result->statusCode,
                $result->body,
                'Validation failed.',
            );
        });

        return $result;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function failRequest(
        string $sourceSystem,
        string $externalRequestId,
        array $payload,
        string $message,
    ): ApiTjStagingAcceptResult {
        $result = ApiTjStagingAcceptResult::serverError($externalRequestId, $message);

        $this->updateRequestSafely(function () use ($sourceSystem, $externalRequestId, $payload, $result, $message) {
            $request = $this->idempotencyService->resolveOrCreate($sourceSystem, $externalRequestId, $payload, self::OPERATION);

            $this->idempotencyService->markFailed(
                $request,
                $result->statusCode,
                $result->body,
                $message,
            );
        });

        return $result;
    }

    private function updateRequestSafely(callable $callback): void
    {
        try {
            DB::transaction($callback);
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
