<?php

namespace App\Http\Controllers;

use App\Models\Municipio;
use App\Models\Oficina;
use App\Models\Tarjeta;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StackController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $isAdmin = $user->hasRole('admin');

        $offices = Oficina::query()
            ->where('tipo', Oficina::TIPO_DELEGACION)
            ->when(! $isAdmin, fn ($query) => $query->where('id', $user->oficina_id))
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'region']);

        $officeIds = $offices->pluck('id')->all();
        $officesById = $offices->keyBy('id');

        $baseCards = Tarjeta::query()
            ->when($officeIds !== [], fn ($query) => $query->whereIn('oficina_id', $officeIds))
            ->when($officeIds === [] && ! $isAdmin, fn ($query) => $query->whereRaw('1 = 0'));

        $centralCount = $isAdmin
            ? Tarjeta::whereHas('oficina', fn ($query) => $query->where('tipo', Oficina::TIPO_CENTRAL))->count()
            : 0;

        $global = [
            'total' => (clone $baseCards)->count() + $centralCount,
            'central' => $centralCount,
            'region' => (clone $baseCards)->whereIn('estatus', [Tarjeta::STATUS_ASIGNADA_OFICINA, Tarjeta::STATUS_DEVUELTA])->count(),
            'capturistas' => (clone $baseCards)->where('estatus', Tarjeta::STATUS_ASIGNADA_USUARIO)->count(),
            'capturadas' => (clone $baseCards)->where('estatus', Tarjeta::STATUS_CONSUMIDA)->count(),
        ];

        $municipioRows = (clone $baseCards)
            ->select([
                'oficina_id',
                'municipio_id',
            ])
            ->selectRaw('COUNT(*) as asignadas')
            ->selectRaw('SUM(CASE WHEN estatus = ? THEN 1 ELSE 0 END) as capturadas', [Tarjeta::STATUS_CONSUMIDA])
            ->whereNotNull('municipio_id')
            ->groupBy('oficina_id', 'municipio_id')
            ->orderBy('oficina_id')
            ->orderBy('municipio_id')
            ->get();

        $municipiosById = Municipio::query()
            ->whereIn('id', $municipioRows->pluck('municipio_id')->filter()->unique()->values())
            ->get(['id', 'nombre', 'region'])
            ->keyBy('id');

        $cardsRoute = $isAdmin
            ? route('admin.inventario.tarjetas.index')
            : route('delegacion.inventario.tarjetas.index');
        $usersRoute = $isAdmin
            ? route('admin.usuarios.index')
            : route('delegacion.usuarios.index');

        return view('stack.index', compact(
            'isAdmin',
            'global',
            'municipioRows',
            'officesById',
            'municipiosById',
            'cardsRoute',
            'usersRoute'
        ));
    }
}
