<?php

namespace App\Services;

use App\Models\Beneficiario;
use App\Models\Proteccion;
use App\Models\ProteccionMovimiento;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProteccionService
{
    public function createBatch(User $actor, User $destino, string $tipo, array $numerosInventario, ?string $observaciones = null): int
    {
        $this->ensureAdmin($actor);
        $this->ensureSkatePlazaUser($destino);

        $tipo = $this->normalizeTipo($tipo);
        $numbers = $this->normalizeInventoryNumbers($numerosInventario);
        $existing = Proteccion::whereIn('numero_inventario', $numbers)->pluck('numero_inventario')->all();

        if ($existing !== []) {
            throw ValidationException::withMessages([
                'numeros_inventario' => 'Ya existen numeros de inventario registrados: '.implode(', ', array_slice($existing, 0, 5)),
            ]);
        }

        return DB::transaction(function () use ($actor, $destino, $tipo, $numbers, $observaciones) {
            foreach ($numbers as $number) {
                $proteccion = Proteccion::create([
                    'id' => (string) Str::uuid(),
                    'tipo' => $tipo,
                    'numero_inventario' => $number,
                    'estatus' => Proteccion::STATUS_DISPONIBLE,
                    'usuario_uuid' => $destino->uuid,
                    'observaciones' => $observaciones,
                ]);

                $this->recordMovement($proteccion, 'alta', $actor, [
                    'to_usuario_uuid' => $destino->uuid,
                    'metadata_json' => ['observaciones' => $observaciones],
                ]);
            }

            return count($numbers);
        });
    }

    public function transferToUser(User $actor, Proteccion $proteccion, User $destino, ?string $observaciones = null): Proteccion
    {
        $this->ensureAdmin($actor);
        $this->ensureSkatePlazaUser($destino);

        if ($proteccion->estatus === Proteccion::STATUS_PRESTADA) {
            throw ValidationException::withMessages([
                'proteccion' => 'No puedes transferir una proteccion con prestamo activo.',
            ]);
        }

        return DB::transaction(function () use ($actor, $proteccion, $destino, $observaciones) {
            $fromUsuarioUuid = $proteccion->usuario_uuid;

            $proteccion->usuario_uuid = $destino->uuid;
            $proteccion->observaciones = $observaciones ?: $proteccion->observaciones;
            $proteccion->save();

            $this->recordMovement($proteccion, 'transferencia_usuario', $actor, [
                'from_usuario_uuid' => $fromUsuarioUuid,
                'to_usuario_uuid' => $destino->uuid,
                'metadata_json' => ['observaciones' => $observaciones],
            ]);

            return $proteccion;
        });
    }

    public function changeAvailability(User $actor, Proteccion $proteccion, string $estatus, ?string $observaciones = null): Proteccion
    {
        $this->ensureAdmin($actor);

        if (! in_array($estatus, [Proteccion::STATUS_DISPONIBLE, Proteccion::STATUS_INACTIVA], true)) {
            throw ValidationException::withMessages([
                'estatus' => 'El estatus solicitado no esta permitido.',
            ]);
        }

        if ($proteccion->estatus === Proteccion::STATUS_PRESTADA) {
            throw ValidationException::withMessages([
                'estatus' => 'No puedes cambiar el estatus de una proteccion con prestamo activo.',
            ]);
        }

        if ($proteccion->estatus === $estatus) {
            return $proteccion;
        }

        return DB::transaction(function () use ($actor, $proteccion, $estatus, $observaciones) {
            $proteccion->estatus = $estatus;
            $proteccion->observaciones = $observaciones ?: $proteccion->observaciones;
            $proteccion->save();

            $this->recordMovement($proteccion, $estatus === Proteccion::STATUS_INACTIVA ? 'inactivacion' : 'reactivacion', $actor, [
                'to_usuario_uuid' => $proteccion->usuario_uuid,
                'metadata_json' => ['observaciones' => $observaciones],
            ]);

            return $proteccion;
        });
    }

    public function prestar(User $actor, Proteccion $proteccion, Beneficiario $beneficiario): Proteccion
    {
        if (! $actor->hasRole('skate_plaza')) {
            throw ValidationException::withMessages([
                'proteccion_id' => 'No tienes permisos para registrar prestamos.',
            ]);
        }

        if ($proteccion->usuario_uuid !== $actor->uuid) {
            throw ValidationException::withMessages([
                'proteccion_id' => 'La proteccion no pertenece a tu inventario.',
            ]);
        }

        if ($proteccion->estatus !== Proteccion::STATUS_DISPONIBLE) {
            throw ValidationException::withMessages([
                'proteccion_id' => 'La proteccion no esta disponible para prestamo.',
            ]);
        }

        $activeLoan = Proteccion::where('beneficiario_id', $beneficiario->id)
            ->where('estatus', Proteccion::STATUS_PRESTADA)
            ->exists();

        if ($activeLoan) {
            throw ValidationException::withMessages([
                'beneficiario_id' => 'El beneficiario ya tiene una proteccion bajo resguardo.',
            ]);
        }

        return DB::transaction(function () use ($actor, $proteccion, $beneficiario) {
            $proteccion->estatus = Proteccion::STATUS_PRESTADA;
            $proteccion->beneficiario_id = $beneficiario->id;
            $proteccion->prestada_at = now();
            $proteccion->save();

            $this->recordMovement($proteccion, 'prestamo', $actor, [
                'to_usuario_uuid' => $proteccion->usuario_uuid,
                'beneficiario_id' => $beneficiario->id,
            ]);

            return $proteccion;
        });
    }

    public function devolver(User $actor, Proteccion $proteccion): Proteccion
    {
        if (! $actor->hasRole('skate_plaza')) {
            throw ValidationException::withMessages([
                'proteccion' => 'No tienes permisos para registrar devoluciones.',
            ]);
        }

        if ($proteccion->usuario_uuid !== $actor->uuid) {
            throw ValidationException::withMessages([
                'proteccion' => 'La proteccion no pertenece a tu inventario.',
            ]);
        }

        if ($proteccion->estatus !== Proteccion::STATUS_PRESTADA || ! $proteccion->beneficiario_id) {
            throw ValidationException::withMessages([
                'proteccion' => 'La proteccion no tiene un prestamo activo.',
            ]);
        }

        return DB::transaction(function () use ($actor, $proteccion) {
            $beneficiarioId = $proteccion->beneficiario_id;

            $proteccion->estatus = Proteccion::STATUS_DISPONIBLE;
            $proteccion->beneficiario_id = null;
            $proteccion->prestada_at = null;
            $proteccion->save();

            $this->recordMovement($proteccion, 'devolucion', $actor, [
                'to_usuario_uuid' => $proteccion->usuario_uuid,
                'beneficiario_id' => $beneficiarioId,
            ]);

            return $proteccion;
        });
    }

    public function normalizeInventoryNumbers(array $numbers): array
    {
        $normalized = collect($numbers)
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->values();

        if ($normalized->isEmpty()) {
            throw ValidationException::withMessages([
                'numeros_inventario' => 'Debes proporcionar al menos un numero de inventario.',
            ]);
        }

        $duplicates = $normalized->duplicates()->values()->all();
        if ($duplicates !== []) {
            throw ValidationException::withMessages([
                'numeros_inventario' => 'Hay numeros de inventario repetidos en la captura.',
            ]);
        }

        return $normalized->all();
    }

    protected function normalizeTipo(string $tipo): string
    {
        $tipo = trim($tipo);

        if ($tipo === '') {
            throw ValidationException::withMessages([
                'tipo' => 'Debes indicar el tipo de proteccion.',
            ]);
        }

        return $tipo;
    }

    protected function ensureAdmin(User $actor): void
    {
        if (! $actor->hasRole('admin')) {
            throw ValidationException::withMessages([
                'usuario' => 'No tienes permisos para administrar protecciones.',
            ]);
        }
    }

    protected function ensureSkatePlazaUser(User $user): void
    {
        if (! $user->hasRole('skate_plaza')) {
            throw ValidationException::withMessages([
                'usuario_uuid' => 'El usuario destino debe tener rol Skate Plaza.',
            ]);
        }
    }

    protected function recordMovement(Proteccion $proteccion, string $tipo, User $actor, array $attributes = []): ProteccionMovimiento
    {
        return ProteccionMovimiento::create(array_merge([
            'id' => (string) Str::uuid(),
            'proteccion_id' => $proteccion->id,
            'tipo' => $tipo,
            'actor_uuid' => $actor->uuid,
        ], $attributes));
    }
}
