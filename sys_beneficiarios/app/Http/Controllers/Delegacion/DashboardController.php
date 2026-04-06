<?php

namespace App\Http\Controllers\Delegacion;

use App\Http\Controllers\Controller;
use App\Models\Tarjeta;
use App\Models\ValeBloc;

class DashboardController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $officeId = $user->oficina_id;

        $stats = [
            'tarjetas_oficina' => Tarjeta::where('oficina_id', $officeId)->count(),
            'tarjetas_usuario' => Tarjeta::where('oficina_id', $officeId)->where('estatus', Tarjeta::STATUS_ASIGNADA_USUARIO)->count(),
            'tarjetas_consumidas' => Tarjeta::where('oficina_id', $officeId)->where('estatus', Tarjeta::STATUS_CONSUMIDA)->count(),
            'tarjetas_pendientes' => Tarjeta::where('oficina_id', $officeId)->whereIn('estatus', [Tarjeta::STATUS_ASIGNADA_OFICINA, Tarjeta::STATUS_DEVUELTA])->count(),
            'vales_oficina' => ValeBloc::where('oficina_id', $officeId)->count(),
            'vales_usuario' => ValeBloc::where('oficina_id', $officeId)->where('estatus', ValeBloc::STATUS_ASIGNADO_USUARIO)->count(),
        ];

        $recentCards = Tarjeta::with('usuario')
            ->where('oficina_id', $officeId)
            ->latest()
            ->limit(10)
            ->get();

        return view('delegacion.dashboard', compact('stats', 'recentCards'));
    }
}
