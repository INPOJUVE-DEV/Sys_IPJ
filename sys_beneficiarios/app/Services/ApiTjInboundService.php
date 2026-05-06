<?php

namespace App\Services;

use App\Models\ApiTjInboundRequest;
use App\Models\Beneficiario;
use App\Models\Domicilio;
use App\Support\ApiTjHelper;
use App\Support\SeccionResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ApiTjInboundService
{
    public function process(array $payload, ?ApiTjInboundRequest $existingRequest = null, bool $force = false): array
    {
        $externalRequestId = (string) ($payload['external_request_id'] ?? '');
        $beneficiarioPayload = (array) ($payload['beneficiario'] ?? []);
        $curpMasked = ApiTjHelper::maskCurp($beneficiarioPayload['curp'] ?? null);
        $requestHash = hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        $audit = $existingRequest ?: new ApiTjInboundRequest([
            'id' => (string) Str::uuid(),
            'external_request_id' => $externalRequestId !== '' ? $externalRequestId : 'missing-'.Str::uuid(),
        ]);

        if (! $existingRequest || $force) {
            $audit->fill([
                'source' => 'api_tj',
                'curp_masked' => $curpMasked,
                'status' => ApiTjInboundRequest::STATUS_RECEIVED,
                'request_hash' => $requestHash,
                'received_at' => now(),
                'created_by_system' => 'api_tj',
                'payload_json' => $payload,
                'error_message' => null,
                'response_code' => null,
            ]);
            $audit->save();
        }

        if (! $force && $existingRequest && in_array($existingRequest->status, [
            ApiTjInboundRequest::STATUS_CREATED,
            ApiTjInboundRequest::STATUS_ALREADY_PROCESSED,
        ], true)) {
            $existingRequest->status = ApiTjInboundRequest::STATUS_ALREADY_PROCESSED;
            $existingRequest->response_code = 200;
            $existingRequest->processed_at = now();
            $existingRequest->save();

            return [
                'status_code' => 200,
                'body' => [
                    'accepted' => true,
                    'status' => ApiTjInboundRequest::STATUS_ALREADY_PROCESSED,
                    'beneficiario_id' => $existingRequest->beneficiario_id,
                    'external_request_id' => $existingRequest->external_request_id,
                ],
            ];
        }

        try {
            $validated = $this->validatePayload($payload);
            $beneficiarioPayload = $validated['beneficiario'];
            $domicilioPayload = $beneficiarioPayload['domicilio'];

            $normalizedCurp = ApiTjHelper::normalizeCurp($beneficiarioPayload['curp']);
            $existingBeneficiario = Beneficiario::where('curp', $normalizedCurp)->first();
            if ($existingBeneficiario && (! $existingRequest || $existingRequest->beneficiario_id !== $existingBeneficiario->id)) {
                $audit->fill([
                    'status' => ApiTjInboundRequest::STATUS_CONFLICT,
                    'response_code' => 409,
                    'error_message' => 'La CURP ya existe en Sys_IPJ',
                    'processed_at' => now(),
                ])->save();

                return [
                    'status_code' => 409,
                    'body' => [
                        'accepted' => false,
                        'status' => 'conflict',
                        'message' => 'La CURP ya existe en Sys_IPJ',
                    ],
                ];
            }

            $seccion = SeccionResolver::resolve($domicilioPayload['seccional'] ?? null);
            if (! $seccion) {
                throw ValidationException::withMessages([
                    'beneficiario.domicilio.seccional' => ['La seccional no existe en Sys_IPJ.'],
                ]);
            }

            if ((string) $domicilioPayload['municipio_id'] !== (string) $seccion->municipio_id) {
                throw ValidationException::withMessages([
                    'beneficiario.domicilio.municipio_id' => ['El municipio no coincide con la seccional proporcionada.'],
                ]);
            }

            $beneficiario = DB::transaction(function () use ($beneficiarioPayload, $domicilioPayload, $seccion, $normalizedCurp) {
                $beneficiario = new Beneficiario([
                    'id' => (string) Str::uuid(),
                    'folio_tarjeta' => trim((string) ($beneficiarioPayload['folio_tarjeta'] ?? '')) ?: null,
                    'tarjeta_id' => null,
                    'nombre' => $beneficiarioPayload['nombre'],
                    'apellido_paterno' => $beneficiarioPayload['apellido_paterno'],
                    'apellido_materno' => $beneficiarioPayload['apellido_materno'],
                    'curp' => $normalizedCurp,
                    'fecha_nacimiento' => $beneficiarioPayload['fecha_nacimiento'],
                    'sexo' => $beneficiarioPayload['sexo'],
                    'discapacidad' => $beneficiarioPayload['discapacidad'],
                    'id_ine' => $beneficiarioPayload['id_ine'],
                    'telefono' => $beneficiarioPayload['telefono'],
                    'municipio_id' => $seccion->municipio_id,
                    'seccion_id' => $seccion->id,
                    'created_by' => null,
                ]);
                $beneficiario->save();

                $domicilio = new Domicilio([
                    'id' => (string) Str::uuid(),
                    'beneficiario_id' => $beneficiario->id,
                    'calle' => $domicilioPayload['calle'],
                    'numero_ext' => $domicilioPayload['numero_ext'],
                    'numero_int' => $domicilioPayload['numero_int'] ?? null,
                    'colonia' => $domicilioPayload['colonia'],
                    'municipio_id' => $seccion->municipio_id,
                    'codigo_postal' => $domicilioPayload['codigo_postal'],
                    'seccion_id' => $seccion->id,
                ]);
                $domicilio->save();

                return $beneficiario;
            });

            $audit->fill([
                'beneficiario_id' => $beneficiario->id,
                'status' => ApiTjInboundRequest::STATUS_CREATED,
                'response_code' => 201,
                'processed_at' => now(),
            ])->save();

            return [
                'status_code' => 201,
                'body' => [
                    'accepted' => true,
                    'status' => 'created',
                    'beneficiario_id' => $beneficiario->id,
                    'external_request_id' => $externalRequestId,
                ],
            ];
        } catch (ValidationException $exception) {
            $audit->fill([
                'status' => ApiTjInboundRequest::STATUS_REJECTED,
                'response_code' => 422,
                'error_message' => json_encode($exception->errors(), JSON_UNESCAPED_UNICODE),
                'processed_at' => now(),
            ])->save();

            return [
                'status_code' => 422,
                'body' => [
                    'accepted' => false,
                    'status' => 'validation_error',
                    'errors' => $exception->errors(),
                ],
            ];
        } catch (\Throwable $exception) {
            Log::error('API_TJ inbound processing error', [
                'external_request_id' => $externalRequestId,
                'curp_masked' => $curpMasked,
                'request_hash' => $requestHash,
                'message' => $exception->getMessage(),
            ]);

            $audit->fill([
                'status' => ApiTjInboundRequest::STATUS_ERROR,
                'response_code' => 500,
                'error_message' => $exception->getMessage(),
                'processed_at' => now(),
            ])->save();

            return [
                'status_code' => 500,
                'body' => [
                    'accepted' => false,
                    'status' => 'error',
                    'message' => 'Error interno al procesar expediente',
                ],
            ];
        }
    }

    private function validatePayload(array $payload): array
    {
        $curpRegex = '/^[A-Z][AEIOUX][A-Z]{2}\d{2}(0[1-9]|1[0-2])(0[1-9]|[12]\d|3[01])[HM](AS|BC|BS|CC|CL|CM|CS|CH|DF|DG|GT|GR|HG|JC|MC|MN|MS|NT|NL|OC|PL|QT|QR|SP|SL|SR|TC|TS|TL|VZ|YN|ZS|NE)[B-DF-HJ-NP-TV-Z]{3}[A-Z\d]\d$/i';

        $validator = Validator::make($payload, [
            'external_request_id' => ['required', 'string', 'max:255'],
            'beneficiario' => ['required', 'array'],
            'beneficiario.curp' => ['required', 'string', 'size:18', 'regex:'.$curpRegex],
            'beneficiario.nombre' => ['required', 'string', 'max:255'],
            'beneficiario.apellido_paterno' => ['required', 'string', 'max:255'],
            'beneficiario.apellido_materno' => ['required', 'string', 'max:255'],
            'beneficiario.fecha_nacimiento' => ['required', 'date'],
            'beneficiario.sexo' => ['required', Rule::in(['M', 'F', 'X'])],
            'beneficiario.discapacidad' => ['required', 'boolean'],
            'beneficiario.id_ine' => ['required', 'string', 'max:255'],
            'beneficiario.telefono' => ['required', 'regex:/^\d{10}$/'],
            'beneficiario.folio_tarjeta' => ['nullable', 'string', 'max:255'],
            'beneficiario.domicilio' => ['required', 'array'],
            'beneficiario.domicilio.calle' => ['required', 'string', 'max:255'],
            'beneficiario.domicilio.numero_ext' => ['required', 'string', 'max:50'],
            'beneficiario.domicilio.numero_int' => ['nullable', 'string', 'max:50'],
            'beneficiario.domicilio.colonia' => ['required', 'string', 'max:255'],
            'beneficiario.domicilio.municipio_id' => ['required', 'exists:municipios,id'],
            'beneficiario.domicilio.codigo_postal' => ['required', 'string', 'max:20'],
            'beneficiario.domicilio.seccional' => ['required', 'string', 'max:20'],
        ]);

        return $validator->validate();
    }
}
