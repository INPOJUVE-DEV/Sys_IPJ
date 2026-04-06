<?php

namespace App\Http\Controllers\SkatePlaza;

use App\Http\Controllers\Controller;
use App\Models\Proteccion;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $stats = [
            'total' => Proteccion::where('usuario_uuid', $user->uuid)->count(),
            'disponibles' => Proteccion::where('usuario_uuid', $user->uuid)
                ->where('estatus', Proteccion::STATUS_DISPONIBLE)
                ->count(),
            'prestadas' => Proteccion::where('usuario_uuid', $user->uuid)
                ->where('estatus', Proteccion::STATUS_PRESTADA)
                ->count(),
        ];

        $availableProtecciones = Proteccion::where('usuario_uuid', $user->uuid)
            ->where('estatus', Proteccion::STATUS_DISPONIBLE)
            ->orderBy('tipo')
            ->orderBy('numero_inventario')
            ->get(['id', 'tipo', 'numero_inventario']);

        $activeLoans = Proteccion::with('beneficiario')
            ->where('usuario_uuid', $user->uuid)
            ->where('estatus', Proteccion::STATUS_PRESTADA)
            ->orderByDesc('prestada_at')
            ->get();

        return view('skate_plaza.dashboard', compact('stats', 'availableProtecciones', 'activeLoans'));
    }
}
