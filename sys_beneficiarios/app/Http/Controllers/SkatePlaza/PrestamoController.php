<?php

namespace App\Http\Controllers\SkatePlaza;

use App\Http\Controllers\Controller;
use App\Models\Beneficiario;
use App\Models\Proteccion;
use App\Services\ProteccionService;
use Illuminate\Http\Request;

class PrestamoController extends Controller
{
    public function store(Request $request, ProteccionService $proteccionService)
    {
        $data = $request->validate([
            'beneficiario_id' => ['required', 'exists:beneficiarios,id'],
            'proteccion_id' => ['required', 'exists:protecciones,id'],
        ]);

        $proteccionService->prestar(
            $request->user(),
            Proteccion::findOrFail($data['proteccion_id']),
            Beneficiario::findOrFail($data['beneficiario_id']),
        );

        return redirect()->route('skate-plaza.home')->with('status', 'Prestamo registrado correctamente');
    }

    public function devolver(Request $request, Proteccion $proteccion, ProteccionService $proteccionService)
    {
        $proteccionService->devolver($request->user(), $proteccion);

        return redirect()->route('skate-plaza.home')->with('status', 'Proteccion devuelta correctamente');
    }
}
