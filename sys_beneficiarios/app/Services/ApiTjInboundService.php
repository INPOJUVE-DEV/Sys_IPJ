<?php

namespace App\Services;

use App\Models\ApiTjInboundRequest;
use App\Models\Beneficiario;
use App\Models\Domicilio;
use App\Models\Tarjeta;
use App\Support\ApiTjHelper;
use App\Support\SeccionResolver;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ApiTjInboundService
{
    public function __construct(
        private readonly ApiTjSyncService $syncService,
    )
    {
    }

    public function processBatch(array $payload, ?ApiTjInboundRequest $existingRequest = null, bool $force = false): array
    {
        $externalRequestId = (string) ($payload['external_request_id'] ?? '');
        $requestHash = hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $audit = $existingRequest ?: new ApiTjInboundRequest([
            'id' => (string) Str::uuid(),
            'external_request_id' => $externalRequestId !== '' ? $externalRequestId : 'missing-'.Str::uuid(),
        ]);

        if (! $force && $existingRequest && $existingRequest->status === ApiTjInboundRequest::STATUS_PROCESSED) {
            return [
                'status_code' => 200,
                'body' => $existingRequest->result_json ?: [
                    'accepted' => true,
                    'external_request_id' => $existingRequest->external_request_id,
                    'total' => $existingRequest->total_count,
                    'accepted_count' => $existingRequest->accepted_count,
                    'rejected_count' => $existingRequest->rejected_count,
                    'results' => [],
                ],
            ];
        }

        $records = (array) ($payload['records'] ?? []);
        $firstCurp = $records[0]['curp'] ?? null;
        $audit->fill([
            'source' => 'api_tj',
            'curp_masked' => ApiTjHelper::maskCurp($firstCurp),
            'status' => ApiTjInboundRequest::STATUS_PENDING,
            'request_hash' => $requestHash,
            'received_at' => now(),
            'created_by_system' => 'api_tj',
            'payload_json' => $payload,
            'result_json' => null,
            'error_message' => null,
            'response_code' => null,
            'total_count' => count($records),
            'accepted_count' => 0,
            'rejected_count' => 0,
            'processed_at' => null,
            'beneficiario_id' => null,
        ]);
        $audit->save();

        try {
            $validated = $this->validateEnvelope($payload);
            $results = collect();
            $beneficiarioIds = [];

            foreach ($validated['records'] as $index => $record) {
                $itemResult = $this->processRecord($record, (int) $index, $validated['external_request_id']);
                $results->push($itemResult);

                if (! empty($itemResult['beneficiario_id'])) {
                    $beneficiarioIds[] = $itemResult['beneficiario_id'];
                }
            }

            $acceptedCount = $results->whereIn('status', ['created', 'updated'])->count();
            $rejectedCount = $results->count() - $acceptedCount;
            $responseBody = [
                'accepted' => $acceptedCount > 0,
                'external_request_id' => $validated['external_request_id'],
                'total' => $results->count(),
                'accepted_count' => $acceptedCount,
                'rejected_count' => $rejectedCount,
                'results' => $results->values()->all(),
            ];

            $audit->fill([
                'status' => ApiTjInboundRequest::STATUS_PROCESSED,
                'response_code' => $rejectedCount === 0 && $results->contains('status', 'created') ? 201 : 200,
                'accepted_count' => $acceptedCount,
                'rejected_count' => $rejectedCount,
                'processed_at' => now(),
                'beneficiario_id' => count($beneficiarioIds) === 1 ? $beneficiarioIds[0] : null,
                'result_json' => $responseBody,
            ])->save();

            return [
                'status_code' => $audit->response_code,
                'body' => $responseBody,
            ];
        } catch (ValidationException $exception) {
            $responseBody = [
                'accepted' => false,
                'external_request_id' => $externalRequestId,
                'total' => count($records),
                'accepted_count' => 0,
                'rejected_count' => count($records),
                'errors' => $exception->errors(),
                'results' => [],
            ];

            $audit->fill([
                'status' => ApiTjInboundRequest::STATUS_FAILED,
                'response_code' => 422,
                'error_message' => json_encode($exception->errors(), JSON_UNESCAPED_UNICODE),
                'accepted_count' => 0,
                'rejected_count' => count($records),
                'processed_at' => now(),
                'result_json' => $responseBody,
            ])->save();

            return [
                'status_code' => 422,
                'body' => $responseBody,
            ];
        } catch (\Throwable $exception) {
            Log::error('API_TJ inbound batch error', [
                'external_request_id' => $externalRequestId,
                'request_hash' => $requestHash,
                'message' => $exception->getMessage(),
            ]);

            $responseBody = [
                'accepted' => false,
                'external_request_id' => $externalRequestId,
                'total' => count($records),
                'accepted_count' => 0,
                'rejected_count' => count($records),
                'message' => 'Error interno al procesar el lote.',
                'results' => [],
            ];

            $audit->fill([
                'status' => ApiTjInboundRequest::STATUS_FAILED,
                'response_code' => 500,
                'error_message' => $exception->getMessage(),
                'accepted_count' => 0,
                'rejected_count' => count($records),
                'processed_at' => now(),
                'result_json' => $responseBody,
            ])->save();

            return [
                'status_code' => 500,
                'body' => $responseBody,
            ];
        }
    }

    private function processRecord(array $record, int $index, string $externalRequestId): array
    {
        try {
            $validated = $this->validateRecord($record);
            $normalizedCurp = ApiTjHelper::normalizeCurp($validated['curp']);
            $curpHash = $this->resolveCurpHash($normalizedCurp);
            $seccion = $this->resolveSeccion($validated['domicilio']);
            $existing = $this->findExistingBeneficiario($normalizedCurp, $curpHash);

            $beneficiario = DB::transaction(function () use ($validated, $normalizedCurp, $curpHash, $seccion, $existing, $externalRequestId) {
                $beneficiario = $existing ?: new Beneficiario([
                    'id' => (string) Str::uuid(),
                    'created_by' => null,
                ]);

                $beneficiario->fill([
                    'nombre' => $validated['nombre'],
                    'apellido_paterno' => $validated['apellido_paterno'],
                    'apellido_materno' => $validated['apellido_materno'],
                    'curp' => $normalizedCurp,
                    'curp_hash' => $curpHash,
                    'fecha_nacimiento' => $validated['fecha_nacimiento'],
                    'sexo' => $validated['sexo'] ?? null,
                    'discapacidad' => (bool) ($validated['discapacidad'] ?? false),
                    'id_ine' => $validated['id_ine'] ?? null,
                    'telefono' => $validated['telefono'],
                    'email' => $validated['email'] ?? null,
                    'municipio_id' => $seccion->municipio_id,
                    'seccion_id' => $seccion->id,
                    'source_system' => 'api_tj',
                    'source_external_request_id' => $externalRequestId,
                    'status' => Beneficiario::STATUS_ACTIVE,
                ]);
                $beneficiario->save();

                $this->upsertDomicilio($beneficiario, $validated['domicilio'], $seccion);
                $this->syncOptionalTarjeta($beneficiario, $validated, $seccion->municipio_id);

                return $beneficiario->fresh(['domicilio', 'tarjeta']);
            });

            if ($beneficiario->hasCompleteApiTjProfile()) {
                $this->syncService->syncBeneficiario($beneficiario);
                $beneficiario = $beneficiario->fresh(['domicilio', 'tarjeta']);
            }

            return [
                'index' => $index,
                'curp' => $normalizedCurp,
                'status' => $existing ? 'updated' : 'created',
                'beneficiario_id' => $beneficiario->id,
                'folio_tarjeta' => $beneficiario->apiTjTarjetaNumero(),
                'sync_status' => $beneficiario->api_tj_sync_status,
            ];
        } catch (ValidationException $exception) {
            return [
                'index' => $index,
                'curp' => ApiTjHelper::normalizeCurp($record['curp'] ?? null),
                'status' => 'rejected',
                'errors' => $exception->errors(),
            ];
        } catch (\DomainException $exception) {
            return [
                'index' => $index,
                'curp' => ApiTjHelper::normalizeCurp($record['curp'] ?? null),
                'status' => 'conflict',
                'message' => $exception->getMessage(),
            ];
        }
    }

    private function validateEnvelope(array $payload): array
    {
        return Validator::make($payload, [
            'external_request_id' => ['required', 'string', 'max:255'],
            'records' => ['required', 'array', 'min:1'],
        ])->validate();
    }

    private function validateRecord(array $record): array
    {
        $curpRegex = '/^[A-Z][AEIOUX][A-Z]{2}\d{2}(0[1-9]|1[0-2])(0[1-9]|[12]\d|3[01])[HM](AS|BC|BS|CC|CL|CM|CS|CH|DF|DG|GT|GR|HG|JC|MC|MN|MS|NT|NL|OC|PL|QT|QR|SP|SL|SR|TC|TS|TL|VZ|YN|ZS|NE)[B-DF-HJ-NP-TV-Z]{3}[A-Z\d]\d$/i';

        return Validator::make($record, [
            'curp' => ['required', 'string', 'size:18', 'regex:'.$curpRegex],
            'nombre' => ['required', 'string', 'max:255'],
            'apellido_paterno' => ['required', 'string', 'max:255'],
            'apellido_materno' => ['required', 'string', 'max:255'],
            'fecha_nacimiento' => ['required', 'date'],
            'telefono' => ['required', 'regex:/^\d{10}$/'],
            'email' => ['nullable', 'email', 'max:255'],
            'sexo' => ['nullable', Rule::in(['M', 'F', 'X'])],
            'discapacidad' => ['nullable', 'boolean'],
            'id_ine' => ['nullable', 'string', 'max:255'],
            'folio_tarjeta' => ['nullable', 'string', 'max:255'],
            'domicilio' => ['required', 'array'],
            'domicilio.calle' => ['required', 'string', 'max:255'],
            'domicilio.numero_ext' => ['required', 'string', 'max:50'],
            'domicilio.numero_int' => ['nullable', 'string', 'max:50'],
            'domicilio.colonia' => ['required', 'string', 'max:255'],
            'domicilio.municipio_id' => ['required', 'exists:municipios,id'],
            'domicilio.codigo_postal' => ['required', 'string', 'max:20'],
            'domicilio.seccional' => ['required', 'string', 'max:20'],
        ])->validate();
    }

    private function resolveCurpHash(string $curp): ?string
    {
        $secret = trim((string) config('api_tj.curp_hash_secret', ''));

        return $secret !== '' ? ApiTjHelper::hashCurp($curp, $secret) : null;
    }

    private function resolveSeccion(array $domicilioPayload): object
    {
        $seccion = SeccionResolver::resolve($domicilioPayload['seccional'] ?? null);
        if (! $seccion) {
            throw ValidationException::withMessages([
                'domicilio.seccional' => ['La seccional no existe en Sys_IPJ.'],
            ]);
        }

        if ((string) $domicilioPayload['municipio_id'] !== (string) $seccion->municipio_id) {
            throw ValidationException::withMessages([
                'domicilio.municipio_id' => ['El municipio no coincide con la seccional proporcionada.'],
            ]);
        }

        return $seccion;
    }

    private function findExistingBeneficiario(string $curp, ?string $curpHash): ?Beneficiario
    {
        $matches = Beneficiario::query()
            ->whereNull('deleted_at')
            ->where('status', Beneficiario::STATUS_ACTIVE)
            ->when($curpHash, function ($query) use ($curpHash, $curp) {
                $query->where(function ($sub) use ($curpHash, $curp) {
                    $sub->where('curp_hash', $curpHash)
                        ->orWhere('curp', $curp);
                });
            }, function ($query) use ($curp) {
                $query->where('curp', $curp);
            })
            ->get();

        if ($matches->count() > 1) {
            throw new \DomainException('Se detectaron multiples beneficiarios activos con la misma CURP o hash.');
        }

        return $matches->first();
    }

    private function upsertDomicilio(Beneficiario $beneficiario, array $domicilioPayload, object $seccion): void
    {
        $domicilio = $beneficiario->domicilio ?: new Domicilio([
            'id' => (string) Str::uuid(),
            'beneficiario_id' => $beneficiario->id,
        ]);

        $domicilio->fill([
            'calle' => $domicilioPayload['calle'],
            'numero_ext' => $domicilioPayload['numero_ext'],
            'numero_int' => $domicilioPayload['numero_int'] ?? null,
            'colonia' => $domicilioPayload['colonia'],
            'municipio_id' => $seccion->municipio_id,
            'codigo_postal' => $domicilioPayload['codigo_postal'],
            'seccion_id' => $seccion->id,
        ]);
        $domicilio->beneficiario_id = $beneficiario->id;
        $domicilio->save();
    }

    private function syncOptionalTarjeta(Beneficiario $beneficiario, array $payload, int $municipioId): void
    {
        $folioTarjeta = trim((string) ($payload['folio_tarjeta'] ?? ''));
        if ($folioTarjeta === '') {
            $folioTarjeta = $beneficiario->apiTjTarjetaNumero() ?? $this->generateDigitalFolio();
        }

        $tarjeta = Tarjeta::firstOrNew(['folio' => $folioTarjeta]);
        if (! $tarjeta->exists) {
            $tarjeta->id = (string) Str::uuid();
        } elseif ($tarjeta->beneficiario_id && $tarjeta->beneficiario_id !== $beneficiario->id) {
            throw new \DomainException('El folio de tarjeta ya esta asignado a otro beneficiario.');
        }

        $tarjeta->estatus = Tarjeta::STATUS_CONSUMIDA;
        $tarjeta->beneficiario_id = $beneficiario->id;
        $tarjeta->municipio_id = $municipioId;
        $tarjeta->source_system = 'api_tj';
        $tarjeta->is_digital = ApiTjHelper::isDigitalFolio($folioTarjeta);
        $tarjeta->save();

        $beneficiario->forceFill([
            'tarjeta_id' => $tarjeta->id,
            'folio_tarjeta' => $tarjeta->folio,
        ])->save();
    }

    private function generateDigitalFolio(): string
    {
        $tarjetaFolio = Tarjeta::query()
            ->where('folio', 'like', 'TD-%')
            ->orderByDesc('folio')
            ->value('folio');
        $beneficiarioFolio = Beneficiario::query()
            ->where('folio_tarjeta', 'like', 'TD-%')
            ->orderByDesc('folio_tarjeta')
            ->value('folio_tarjeta');

        $currentMax = max(
            $this->extractDigitalSequence($tarjetaFolio),
            $this->extractDigitalSequence($beneficiarioFolio)
        );

        $next = $currentMax + 1;
        if ($next > 99999) {
            throw new \DomainException('Ya no hay folios digitales disponibles con el formato TD-XXXXX.');
        }

        return sprintf('TD-%05d', $next);
    }

    private function extractDigitalSequence(?string $folio): int
    {
        $folio = strtoupper(trim((string) $folio));
        if (! preg_match('/^TD-(\d{5})$/', $folio, $matches)) {
            return 0;
        }

        return (int) $matches[1];
    }
}
