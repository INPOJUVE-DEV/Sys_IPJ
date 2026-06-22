<?php

namespace App\Services\Beneficiarios;

use App\Models\Beneficiario;
use App\Models\Domicilio;
use App\Models\Integrations\IntegrationInboundRequest;
use App\Models\Seccion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BeneficiarioRegistrationService
{
    public function __construct(
        private readonly BeneficiarioLocationResolver $locationResolver,
    ) {
    }

    /**
     * @param  array<string, mixed>  $beneficiarioData
     * @param  array<string, mixed>  $domicilioData
     */
    public function create(array $beneficiarioData, array $domicilioData, User $actor): Beneficiario
    {
        return DB::transaction(function () use ($beneficiarioData, $domicilioData, $actor) {
            $beneficiario = new Beneficiario();
            $beneficiario->id = (string) Str::uuid();
            $beneficiario->created_by = $actor->uuid;

            return $this->persist($beneficiario, $beneficiarioData, $domicilioData, $actor);
        });
    }

    /**
     * @param  array<string, mixed>  $beneficiarioData
     * @param  array<string, mixed>  $domicilioData
     */
    public function update(Beneficiario $beneficiario, array $beneficiarioData, array $domicilioData, User $actor): Beneficiario
    {
        return DB::transaction(fn () => $this->persist($beneficiario, $beneficiarioData, $domicilioData, $actor, $beneficiario->seccion));
    }

    /**
     * @param  array<string, mixed>  $beneficiarioData
     * @param  array<string, mixed>  $domicilioData
     */
    public function upsertByCurp(array $beneficiarioData, array $domicilioData, User $actor): Beneficiario
    {
        return DB::transaction(function () use ($beneficiarioData, $domicilioData, $actor) {
            $curp = (string) ($beneficiarioData['curp'] ?? '');

            $beneficiario = Beneficiario::query()
                ->where('curp', $curp)
                ->lockForUpdate()
                ->first();

            if (! $beneficiario) {
                $beneficiario = new Beneficiario();
                $beneficiario->id = (string) Str::uuid();
                $beneficiario->created_by = $actor->uuid;
            }

            return $this->persist($beneficiario, $beneficiarioData, $domicilioData, $actor, resetTarjetaId: true);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createFromIntegration(array $data, User $technicalActor, IntegrationInboundRequest $request): Beneficiario
    {
        $beneficiarioData = $data['beneficiario'] ?? [];
        $domicilioData = is_array($beneficiarioData['domicilio'] ?? null)
            ? $beneficiarioData['domicilio']
            : [];

        unset($beneficiarioData['domicilio']);

        return $this->create($beneficiarioData, $domicilioData, $technicalActor);
    }

    /**
     * @param  array<string, mixed>  $beneficiarioData
     * @param  array<string, mixed>  $domicilioData
     */
    private function persist(
        Beneficiario $beneficiario,
        array $beneficiarioData,
        array $domicilioData,
        User $actor,
        ?Seccion $fallbackSeccion = null,
        bool $resetTarjetaId = false,
    ): Beneficiario {
        $normalizedData = $this->normalizeBeneficiarioData($beneficiarioData);
        $seccion = $this->locationResolver->resolve(
            domicilio: $domicilioData,
            idIne: $normalizedData['id_ine'] ?? null,
            actor: $actor,
            fallback: $fallbackSeccion,
        );
        $municipioId = $seccion?->municipio_id
            ?? ($domicilioData['municipio_id'] ?? $beneficiario->municipio_id);

        $beneficiario->fill($normalizedData);
        $beneficiario->seccion()->associate($seccion);
        $beneficiario->municipio_id = $municipioId;
        if ($resetTarjetaId) {
            $beneficiario->tarjeta_id = null;
        }
        $beneficiario->save();

        $this->saveDomicilio($beneficiario, $domicilioData, $seccion, $municipioId);

        return $beneficiario->fresh(['domicilio', 'seccion', 'municipio', 'tarjeta']);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizeBeneficiarioData(array $data): array
    {
        if (array_key_exists('folio_tarjeta', $data)) {
            $data['folio_tarjeta'] = trim((string) ($data['folio_tarjeta'] ?? '')) ?: null;
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $domicilioData
     */
    private function saveDomicilio(Beneficiario $beneficiario, array $domicilioData, ?Seccion $seccion, mixed $municipioId): void
    {
        $payload = array_filter([
            'calle' => $domicilioData['calle'] ?? null,
            'numero_ext' => $domicilioData['numero_ext'] ?? null,
            'numero_int' => $domicilioData['numero_int'] ?? null,
            'colonia' => $domicilioData['colonia'] ?? null,
            'municipio_id' => $municipioId,
            'codigo_postal' => $domicilioData['codigo_postal'] ?? null,
            'seccion_id' => $seccion?->id,
        ], fn ($value) => ! is_null($value));

        if ($payload === []) {
            return;
        }

        $domicilio = $beneficiario->domicilio ?: new Domicilio([
            'id' => (string) Str::uuid(),
            'beneficiario_id' => $beneficiario->id,
        ]);

        $domicilio->fill($payload);
        $domicilio->beneficiario_id = $beneficiario->id;
        $domicilio->save();
    }
}
