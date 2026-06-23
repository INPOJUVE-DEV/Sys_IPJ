<?php

namespace App\Services\Integrations\ApiTj;

use App\Models\Beneficiario;
use App\Models\Tarjeta;
use DateTimeInterface;

class CardholderPayloadFactory
{
    public function __construct(
        private readonly CurpFingerprintService $fingerprintService,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function makeItem(Beneficiario $beneficiario, ?DateTimeInterface $syncedAt = null): array
    {
        $cardNumber = $this->resolveCardNumber($beneficiario);
        if ($cardNumber === null) {
            throw new SkipSyncItemException('Beneficiary does not have a valid card number for outbound sync.');
        }

        return [
            'curp_hash' => $this->fingerprintService->hash((string) $beneficiario->curp),
            'curp_masked' => $this->fingerprintService->mask((string) $beneficiario->curp),
            'tarjeta_numero' => $cardNumber,
            'status' => 'active',
            'synced_at' => ($syncedAt ?? now())->format(DATE_ATOM),
            'nombres' => $this->cleanString($beneficiario->nombre),
            'apellido' => $this->fullApellido($beneficiario),
            'municipio_id' => $beneficiario->municipio_id ? (int) $beneficiario->municipio_id : null,
        ];
    }

    private function fullApellido(Beneficiario $beneficiario): ?string
    {
        $apellido = trim(collect([
            $beneficiario->apellido_paterno,
            $beneficiario->apellido_materno,
        ])->filter(fn ($value) => trim((string) $value) !== '')->implode(' '));

        return $apellido !== '' ? $apellido : null;
    }

    private function cleanString(?string $value): ?string
    {
        $clean = trim((string) $value);

        return $clean !== '' ? $clean : null;
    }

    private function resolveCardNumber(Beneficiario $beneficiario): ?string
    {
        $relatedCard = $beneficiario->relationLoaded('tarjeta')
            ? $beneficiario->tarjeta
            : ($beneficiario->tarjeta_id ? Tarjeta::query()->find($beneficiario->tarjeta_id) : null);

        if ($this->isValidConsumedCard($relatedCard)) {
            return trim((string) $relatedCard->folio);
        }

        $consumedCard = Tarjeta::query()
            ->where('beneficiario_id', $beneficiario->id)
            ->where('estatus', Tarjeta::STATUS_CONSUMIDA)
            ->orderByDesc('updated_at')
            ->first();

        if ($this->isValidConsumedCard($consumedCard)) {
            return trim((string) $consumedCard->folio);
        }

        $fallback = trim((string) $beneficiario->folio_tarjeta);

        return $fallback !== '' ? $fallback : null;
    }

    private function isValidConsumedCard(?Tarjeta $tarjeta): bool
    {
        if (! $tarjeta) {
            return false;
        }

        return $tarjeta->estatus === Tarjeta::STATUS_CONSUMIDA
            && trim((string) $tarjeta->folio) !== '';
    }
}
