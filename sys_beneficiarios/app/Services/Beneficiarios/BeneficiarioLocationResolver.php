<?php

namespace App\Services\Beneficiarios;

use App\Models\Seccion;
use App\Models\User;
use App\Support\SeccionResolver;
use Illuminate\Validation\ValidationException;

class BeneficiarioLocationResolver
{
    public function resolve(array $domicilio, ?string $idIne = null, ?User $actor = null, ?Seccion $fallback = null): Seccion
    {
        $seccion = SeccionResolver::resolveFromIne($idIne)
            ?: SeccionResolver::resolve($domicilio['seccional'] ?? null)
            ?: $fallback;

        if (! $seccion) {
            throw ValidationException::withMessages([
                'id_ine' => 'No fue posible detectar una seccional valida a partir del ID INE.',
            ]);
        }

        $seccion->loadMissing('municipio');

        $inputMunicipioId = $domicilio['municipio_id'] ?? null;
        if ($inputMunicipioId && (string) $inputMunicipioId !== (string) $seccion->municipio_id) {
            throw ValidationException::withMessages([
                'domicilio.municipio_id' => 'El municipio no coincide con la seccional detectada.',
            ]);
        }

        $this->ensureActorCanCaptureSeccion($actor, $seccion);

        return $seccion;
    }

    public function ensureActorCanCaptureSeccion(?User $actor, Seccion $seccion): void
    {
        if (! $actor?->hasAnyRole(['delegado', 'capturista', 'capturista_programas']) || ! $actor->oficina_id) {
            return;
        }

        $officeId = $seccion->municipio?->oficina_id;
        if ($officeId && (int) $officeId !== (int) $actor->oficina_id) {
            throw ValidationException::withMessages([
                'id_ine' => 'La seccional detectada no pertenece a tu oficina.',
            ]);
        }
    }
}
