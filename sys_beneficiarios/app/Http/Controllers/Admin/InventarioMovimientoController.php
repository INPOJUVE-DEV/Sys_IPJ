<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProteccionMovimiento;
use App\Models\TarjetaMovimiento;
use App\Models\ValeMovimiento;

class InventarioMovimientoController extends Controller
{
    public function index()
    {
        $tarjetaMovimientos = TarjetaMovimiento::with(['tarjeta', 'actor'])
            ->latest()
            ->limit(50)
            ->get()
            ->map(function ($movement) {
                return [
                    'tipo_recurso' => 'tarjeta',
                    'folio' => $movement->tarjeta?->folio,
                    'tipo' => $movement->tipo,
                    'actor' => $movement->actor?->name,
                    'created_at' => $movement->created_at,
                ];
            });

        $valeMovimientos = ValeMovimiento::with(['valeBloc', 'actor'])
            ->latest()
            ->limit(50)
            ->get()
            ->map(function ($movement) {
                return [
                    'tipo_recurso' => 'vale',
                    'folio' => $movement->valeBloc ? ($movement->valeBloc->folio_inicio.'-'.$movement->valeBloc->folio_fin) : null,
                    'tipo' => $movement->tipo,
                    'actor' => $movement->actor?->name,
                    'created_at' => $movement->created_at,
                ];
            });

        $proteccionMovimientos = ProteccionMovimiento::with(['proteccion', 'actor'])
            ->latest()
            ->limit(50)
            ->get()
            ->map(function ($movement) {
                return [
                    'tipo_recurso' => 'proteccion',
                    'folio' => $movement->proteccion?->numero_inventario,
                    'tipo' => $movement->tipo,
                    'actor' => $movement->actor?->name,
                    'created_at' => $movement->created_at,
                ];
            });

        $movimientos = $tarjetaMovimientos
            ->concat($valeMovimientos)
            ->concat($proteccionMovimientos)
            ->sortByDesc('created_at')
            ->values();

        return view('admin.inventario.movimientos.index', compact('movimientos'));
    }
}
