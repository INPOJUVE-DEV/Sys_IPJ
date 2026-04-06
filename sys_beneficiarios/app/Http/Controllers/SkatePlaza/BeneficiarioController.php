<?php

namespace App\Http\Controllers\SkatePlaza;

use App\Http\Controllers\Controller;
use App\Models\Beneficiario;
use App\Models\Proteccion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BeneficiarioController extends Controller
{
    public function search(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tipo_busqueda' => ['required', 'in:curp,folio_tarjeta'],
            'valor' => ['required', 'string', 'max:255'],
        ]);

        $value = trim($data['valor']);
        if ($data['tipo_busqueda'] === 'curp') {
            $request->validate(['valor' => ['size:18']]);
            $value = strtoupper($value);
        }

        $beneficiario = Beneficiario::where($data['tipo_busqueda'], $value)->first();

        if (! $beneficiario) {
            return response()->json([
                'message' => 'No se encontro un beneficiario con el dato capturado.',
            ], 404);
        }

        $activeLoan = Proteccion::with('usuario')
            ->where('beneficiario_id', $beneficiario->id)
            ->where('estatus', Proteccion::STATUS_PRESTADA)
            ->first();

        return response()->json([
            'id' => $beneficiario->id,
            'nombre_completo' => trim(sprintf(
                '%s %s %s',
                $beneficiario->nombre,
                $beneficiario->apellido_paterno,
                $beneficiario->apellido_materno
            )),
            'folio_tarjeta' => $beneficiario->folio_tarjeta,
            'curp' => $beneficiario->curp,
            'prestamo_activo' => $activeLoan ? [
                'proteccion_id' => $activeLoan->id,
                'numero_inventario' => $activeLoan->numero_inventario,
                'tipo' => $activeLoan->tipo,
                'prestada_at' => optional($activeLoan->prestada_at)->toIso8601String(),
                'usuario_uuid' => $activeLoan->usuario_uuid,
                'usuario_nombre' => $activeLoan->usuario?->name,
                'puede_devolver' => $activeLoan->usuario_uuid === $request->user()->uuid,
            ] : null,
        ]);
    }
}
