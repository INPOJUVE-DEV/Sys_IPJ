<?php

namespace App\Services;

use App\Models\Beneficiario;
use App\Models\Oficina;
use App\Models\Tarjeta;
use App\Models\TarjetaMovimiento;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TarjetaService
{
    public function createRange(User $actor, Oficina $oficina, string $prefijo, int $desde, int $hasta, int $padding = 0, ?string $observaciones = null): int
    {
        $folios = $this->buildFolios($prefijo, $desde, $hasta, $padding);
        $existing = Tarjeta::whereIn('folio', $folios)->pluck('folio')->all();

        if ($existing !== []) {
            throw ValidationException::withMessages([
                'folio_desde' => 'Ya existen folios en inventario: '.implode(', ', array_slice($existing, 0, 5)),
            ]);
        }

        $status = $oficina->tipo === Oficina::TIPO_CENTRAL
            ? Tarjeta::STATUS_DISPONIBLE
            : Tarjeta::STATUS_ASIGNADA_OFICINA;

        return DB::transaction(function () use ($actor, $folios, $oficina, $status, $observaciones) {
            foreach ($folios as $folio) {
                $tarjeta = Tarjeta::create([
                    'id' => (string) Str::uuid(),
                    'folio' => $folio,
                    'estatus' => $status,
                    'oficina_id' => $oficina->id,
                    'observaciones' => $observaciones,
                ]);

                $this->recordMovement($tarjeta, 'alta', $actor, [
                    'to_oficina_id' => $oficina->id,
                    'metadata_json' => ['observaciones' => $observaciones],
                ]);
            }

            return count($folios);
        });
    }

    public function transferRange(User $actor, Oficina $destino, string $prefijo, int $desde, int $hasta, int $padding = 0, ?string $observaciones = null): int
    {
        $tarjetas = $this->loadRange($prefijo, $desde, $hasta, $padding);

        return DB::transaction(function () use ($actor, $destino, $tarjetas, $observaciones) {
            foreach ($tarjetas as $tarjeta) {
                $fromOficinaId = $tarjeta->oficina_id;
                $fromUsuarioUuid = $tarjeta->usuario_uuid;

                $tarjeta->oficina_id = $destino->id;
                $tarjeta->usuario_uuid = null;
                $tarjeta->estatus = $destino->tipo === Oficina::TIPO_CENTRAL
                    ? Tarjeta::STATUS_DISPONIBLE
                    : Tarjeta::STATUS_ASIGNADA_OFICINA;
                $tarjeta->observaciones = $observaciones ?: $tarjeta->observaciones;
                $tarjeta->save();

                $this->recordMovement($tarjeta, 'transferencia_oficina', $actor, [
                    'from_oficina_id' => $fromOficinaId,
                    'to_oficina_id' => $destino->id,
                    'from_usuario_uuid' => $fromUsuarioUuid,
                    'metadata_json' => ['observaciones' => $observaciones],
                ]);
            }

            return $tarjetas->count();
        });
    }

    public function assignRangeToUser(User $actor, User $destino, string $prefijo, int $desde, int $hasta, int $padding = 0, ?string $observaciones = null): int
    {
        if (! $destino->oficina_id) {
            throw ValidationException::withMessages([
                'usuario_uuid' => 'El usuario destino no tiene oficina asignada.',
            ]);
        }

        $tarjetas = $this->loadRange($prefijo, $desde, $hasta, $padding);
        $this->ensureCanManageTarjetas($actor, $tarjetas);

        return DB::transaction(function () use ($actor, $destino, $tarjetas, $observaciones) {
            foreach ($tarjetas as $tarjeta) {
                $fromOficinaId = $tarjeta->oficina_id;
                $fromUsuarioUuid = $tarjeta->usuario_uuid;

                $tarjeta->oficina_id = $destino->oficina_id;
                $tarjeta->usuario_uuid = $destino->uuid;
                $tarjeta->estatus = Tarjeta::STATUS_ASIGNADA_USUARIO;
                $tarjeta->observaciones = $observaciones ?: $tarjeta->observaciones;
                $tarjeta->save();

                $this->recordMovement($tarjeta, 'asignacion_usuario', $actor, [
                    'from_oficina_id' => $fromOficinaId,
                    'to_oficina_id' => $destino->oficina_id,
                    'from_usuario_uuid' => $fromUsuarioUuid,
                    'to_usuario_uuid' => $destino->uuid,
                    'metadata_json' => ['observaciones' => $observaciones],
                ]);
            }

            return $tarjetas->count();
        });
    }

    public function markStatus(User $actor, Tarjeta $tarjeta, string $estatus, ?string $observaciones = null): Tarjeta
    {
        $this->ensureCanManageTarjetas($actor, collect([$tarjeta]));

        if (! in_array($estatus, [
            Tarjeta::STATUS_DEVUELTA,
            Tarjeta::STATUS_EXTRAVIADA,
            Tarjeta::STATUS_BLOQUEADA,
        ], true)) {
            throw ValidationException::withMessages([
                'estatus' => 'El estatus solicitado no esta permitido.',
            ]);
        }

        return DB::transaction(function () use ($actor, $tarjeta, $estatus, $observaciones) {
            $fromUsuarioUuid = $tarjeta->usuario_uuid;

            $tarjeta->estatus = $estatus;
            if ($estatus === Tarjeta::STATUS_DEVUELTA) {
                $tarjeta->usuario_uuid = null;
            }
            $tarjeta->observaciones = $observaciones ?: $tarjeta->observaciones;
            $tarjeta->save();

            $this->recordMovement($tarjeta, $estatus, $actor, [
                'from_oficina_id' => $tarjeta->oficina_id,
                'to_oficina_id' => $tarjeta->oficina_id,
                'from_usuario_uuid' => $fromUsuarioUuid,
                'to_usuario_uuid' => $tarjeta->usuario_uuid,
                'metadata_json' => ['observaciones' => $observaciones],
            ]);

            return $tarjeta;
        });
    }

    public function findConsumableByFolio(?string $folio, User $actor): ?Tarjeta
    {
        $folio = trim((string) $folio);
        if ($folio === '') {
            return null;
        }

        $tarjeta = Tarjeta::where('folio', $folio)->lockForUpdate()->first();
        if (! $tarjeta) {
            throw ValidationException::withMessages([
                'folio_tarjeta' => 'El folio no existe en inventario.',
            ]);
        }

        if (in_array($tarjeta->estatus, [
            Tarjeta::STATUS_CONSUMIDA,
            Tarjeta::STATUS_BLOQUEADA,
            Tarjeta::STATUS_EXTRAVIADA,
        ], true)) {
            throw ValidationException::withMessages([
                'folio_tarjeta' => 'La tarjeta no esta disponible para captura.',
            ]);
        }

        if ($actor->hasRole('admin')) {
            return $tarjeta;
        }

        if (! $actor->oficina_id) {
            throw ValidationException::withMessages([
                'folio_tarjeta' => 'Tu usuario no tiene oficina asignada para consumir tarjetas.',
            ]);
        }

        $usable = ($tarjeta->usuario_uuid === $actor->uuid && $tarjeta->estatus === Tarjeta::STATUS_ASIGNADA_USUARIO)
            || ($tarjeta->oficina_id === $actor->oficina_id && in_array($tarjeta->estatus, [
                Tarjeta::STATUS_ASIGNADA_OFICINA,
                Tarjeta::STATUS_DEVUELTA,
            ], true));

        if (! $usable) {
            throw ValidationException::withMessages([
                'folio_tarjeta' => 'La tarjeta no pertenece al inventario disponible de tu oficina o asignacion.',
            ]);
        }

        return $tarjeta;
    }

    public function consume(User $actor, Tarjeta $tarjeta, Beneficiario $beneficiario): Tarjeta
    {
        if ($tarjeta->beneficiario_id && $tarjeta->beneficiario_id !== $beneficiario->id) {
            throw ValidationException::withMessages([
                'folio_tarjeta' => 'La tarjeta ya esta ligada a otro beneficiario.',
            ]);
        }

        $fromOficinaId = $tarjeta->oficina_id;
        $fromUsuarioUuid = $tarjeta->usuario_uuid;

        $tarjeta->estatus = Tarjeta::STATUS_CONSUMIDA;
        $tarjeta->beneficiario_id = $beneficiario->id;
        $tarjeta->oficina_id = $actor->oficina_id ?: $tarjeta->oficina_id;
        $tarjeta->usuario_uuid = $actor->uuid;
        $tarjeta->save();

        $this->recordMovement($tarjeta, 'consumo', $actor, [
            'from_oficina_id' => $fromOficinaId,
            'to_oficina_id' => $tarjeta->oficina_id,
            'from_usuario_uuid' => $fromUsuarioUuid,
            'to_usuario_uuid' => $actor->uuid,
            'beneficiario_id' => $beneficiario->id,
        ]);

        return $tarjeta;
    }

    public function backfillConsumedCard(Beneficiario $beneficiario, Oficina $fallbackOficina, User $actor): Tarjeta
    {
        $tarjeta = Tarjeta::firstOrNew(['folio' => $beneficiario->folio_tarjeta]);
        if (! $tarjeta->exists) {
            $tarjeta->id = (string) Str::uuid();
        }

        $tarjeta->estatus = Tarjeta::STATUS_CONSUMIDA;
        $tarjeta->oficina_id = $tarjeta->oficina_id ?: $fallbackOficina->id;
        $tarjeta->usuario_uuid = $tarjeta->usuario_uuid ?: $beneficiario->created_by;
        $tarjeta->municipio_id = $tarjeta->municipio_id ?: $beneficiario->municipio_id;
        $tarjeta->beneficiario_id = $beneficiario->id;
        $tarjeta->save();

        if (! TarjetaMovimiento::where('tarjeta_id', $tarjeta->id)->where('tipo', 'backfill_consumo')->exists()) {
            $this->recordMovement($tarjeta, 'backfill_consumo', $actor, [
                'to_oficina_id' => $tarjeta->oficina_id,
                'to_usuario_uuid' => $tarjeta->usuario_uuid,
                'beneficiario_id' => $beneficiario->id,
            ]);
        }

        return $tarjeta;
    }

    public function buildFolios(string $prefijo, int $desde, int $hasta, int $padding = 0): array
    {
        if ($desde > $hasta) {
            throw ValidationException::withMessages([
                'folio_desde' => 'El rango de folios no es valido.',
            ]);
        }

        return collect(range($desde, $hasta))
            ->map(fn (int $value) => $prefijo.($padding > 0 ? str_pad((string) $value, $padding, '0', STR_PAD_LEFT) : (string) $value))
            ->all();
    }

    protected function loadRange(string $prefijo, int $desde, int $hasta, int $padding = 0): Collection
    {
        $folios = $this->buildFolios($prefijo, $desde, $hasta, $padding);
        $tarjetas = Tarjeta::whereIn('folio', $folios)->get()->keyBy('folio');
        $missing = array_values(array_filter($folios, fn (string $folio) => ! $tarjetas->has($folio)));

        if ($missing !== []) {
            throw ValidationException::withMessages([
                'folio_desde' => 'No se encontraron todas las tarjetas del rango solicitado.',
            ]);
        }

        return collect($folios)->map(fn (string $folio) => $tarjetas->get($folio));
    }

    protected function ensureCanManageTarjetas(User $actor, Collection $tarjetas): void
    {
        if ($actor->hasRole('admin')) {
            return;
        }

        if (! $actor->hasRole('delegado') || ! $actor->oficina_id) {
            throw ValidationException::withMessages([
                'tarjetas' => 'No tienes permisos para administrar estas tarjetas.',
            ]);
        }

        $invalid = $tarjetas->contains(fn (Tarjeta $tarjeta) => $tarjeta->oficina_id !== $actor->oficina_id);
        if ($invalid) {
            throw ValidationException::withMessages([
                'tarjetas' => 'Solo puedes administrar tarjetas de tu delegacion.',
            ]);
        }
    }

    protected function recordMovement(Tarjeta $tarjeta, string $tipo, User $actor, array $attributes = []): TarjetaMovimiento
    {
        return TarjetaMovimiento::create(array_merge([
            'id' => (string) Str::uuid(),
            'tarjeta_id' => $tarjeta->id,
            'tipo' => $tipo,
            'actor_uuid' => $actor->uuid,
        ], $attributes));
    }
}
