<?php

namespace App\Services;

use App\Models\Oficina;
use App\Models\User;
use App\Models\ValeBloc;
use App\Models\ValeMovimiento;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ValeBlocService
{
    public function createBlock(User $actor, Oficina $oficina, int $folioInicio, int $folioFin, ?string $observaciones = null): ValeBloc
    {
        $this->validateRange($folioInicio, $folioFin);

        $overlap = ValeBloc::where('folio_inicio', '<=', $folioFin)
            ->where('folio_fin', '>=', $folioInicio)
            ->exists();

        if ($overlap) {
            throw ValidationException::withMessages([
                'folio_inicio' => 'El rango de vales se sobrepone con un bloc existente.',
            ]);
        }

        $estatus = $oficina->tipo === Oficina::TIPO_CENTRAL
            ? ValeBloc::STATUS_DISPONIBLE
            : ValeBloc::STATUS_ASIGNADO_OFICINA;

        return DB::transaction(function () use ($actor, $oficina, $folioInicio, $folioFin, $estatus, $observaciones) {
            $bloc = ValeBloc::create([
                'id' => (string) Str::uuid(),
                'folio_inicio' => $folioInicio,
                'folio_fin' => $folioFin,
                'cantidad' => 1000,
                'estatus' => $estatus,
                'oficina_id' => $oficina->id,
                'observaciones' => $observaciones,
            ]);

            $this->recordMovement($bloc, 'alta', $actor, [
                'to_oficina_id' => $oficina->id,
                'metadata_json' => ['observaciones' => $observaciones],
            ]);

            return $bloc;
        });
    }

    public function transfer(User $actor, ValeBloc $bloc, Oficina $destino, ?string $observaciones = null): ValeBloc
    {
        $this->ensureCanManageBloc($actor, $bloc);

        return DB::transaction(function () use ($actor, $bloc, $destino, $observaciones) {
            $fromOficinaId = $bloc->oficina_id;
            $fromUsuarioUuid = $bloc->usuario_uuid;

            $bloc->oficina_id = $destino->id;
            $bloc->usuario_uuid = null;
            $bloc->estatus = $destino->tipo === Oficina::TIPO_CENTRAL
                ? ValeBloc::STATUS_DISPONIBLE
                : ValeBloc::STATUS_ASIGNADO_OFICINA;
            $bloc->observaciones = $observaciones ?: $bloc->observaciones;
            $bloc->save();

            $this->recordMovement($bloc, 'transferencia_oficina', $actor, [
                'from_oficina_id' => $fromOficinaId,
                'to_oficina_id' => $destino->id,
                'from_usuario_uuid' => $fromUsuarioUuid,
                'metadata_json' => ['observaciones' => $observaciones],
            ]);

            return $bloc;
        });
    }

    public function assignToUser(User $actor, ValeBloc $bloc, User $destino, ?string $observaciones = null): ValeBloc
    {
        $this->ensureCanManageBloc($actor, $bloc);

        if (! $destino->oficina_id) {
            throw ValidationException::withMessages([
                'usuario_uuid' => 'El usuario destino no tiene oficina asignada.',
            ]);
        }

        return DB::transaction(function () use ($actor, $bloc, $destino, $observaciones) {
            $fromOficinaId = $bloc->oficina_id;
            $fromUsuarioUuid = $bloc->usuario_uuid;

            $bloc->oficina_id = $destino->oficina_id;
            $bloc->usuario_uuid = $destino->uuid;
            $bloc->estatus = ValeBloc::STATUS_ASIGNADO_USUARIO;
            $bloc->observaciones = $observaciones ?: $bloc->observaciones;
            $bloc->save();

            $this->recordMovement($bloc, 'asignacion_usuario', $actor, [
                'from_oficina_id' => $fromOficinaId,
                'to_oficina_id' => $destino->oficina_id,
                'from_usuario_uuid' => $fromUsuarioUuid,
                'to_usuario_uuid' => $destino->uuid,
                'metadata_json' => ['observaciones' => $observaciones],
            ]);

            return $bloc;
        });
    }

    public function markStatus(User $actor, ValeBloc $bloc, string $estatus, ?string $observaciones = null): ValeBloc
    {
        $this->ensureCanManageBloc($actor, $bloc);

        if (! in_array($estatus, [
            ValeBloc::STATUS_CERRADO,
            ValeBloc::STATUS_DEVUELTO,
            ValeBloc::STATUS_EXTRAVIADO,
            ValeBloc::STATUS_BLOQUEADO,
        ], true)) {
            throw ValidationException::withMessages([
                'estatus' => 'El estatus solicitado no esta permitido.',
            ]);
        }

        return DB::transaction(function () use ($actor, $bloc, $estatus, $observaciones) {
            $fromUsuarioUuid = $bloc->usuario_uuid;

            $bloc->estatus = $estatus;
            if ($estatus === ValeBloc::STATUS_DEVUELTO) {
                $bloc->usuario_uuid = null;
            }
            $bloc->observaciones = $observaciones ?: $bloc->observaciones;
            $bloc->save();

            $this->recordMovement($bloc, $estatus, $actor, [
                'from_oficina_id' => $bloc->oficina_id,
                'to_oficina_id' => $bloc->oficina_id,
                'from_usuario_uuid' => $fromUsuarioUuid,
                'to_usuario_uuid' => $bloc->usuario_uuid,
                'metadata_json' => ['observaciones' => $observaciones],
            ]);

            return $bloc;
        });
    }

    protected function validateRange(int $folioInicio, int $folioFin): void
    {
        if ($folioFin <= $folioInicio || ($folioFin - $folioInicio) !== 999) {
            throw ValidationException::withMessages([
                'folio_inicio' => 'Cada bloc de vales debe cubrir exactamente 1000 folios consecutivos.',
            ]);
        }
    }

    protected function ensureCanManageBloc(User $actor, ValeBloc $bloc): void
    {
        if ($actor->hasRole('admin')) {
            return;
        }

        if (! $actor->hasRole('delegado') || ! $actor->oficina_id || $bloc->oficina_id !== $actor->oficina_id) {
            throw ValidationException::withMessages([
                'vale_bloc' => 'No tienes permisos para administrar este bloc.',
            ]);
        }
    }

    protected function recordMovement(ValeBloc $bloc, string $tipo, User $actor, array $attributes = []): ValeMovimiento
    {
        return ValeMovimiento::create(array_merge([
            'id' => (string) Str::uuid(),
            'vale_bloc_id' => $bloc->id,
            'tipo' => $tipo,
            'actor_uuid' => $actor->uuid,
        ], $attributes));
    }
}
