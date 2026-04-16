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
            'incidencias' => (clone $baseCards)->whereIn('estatus', [Tarjeta::STATUS_BLOQUEADA, Tarjeta::STATUS_EXTRAVIADA])->count(),
        ];

        $officeStatus = (clone $baseCards)
            ->select(['oficina_id', 'estatus', DB::raw('COUNT(*) as total')])
            ->groupBy('oficina_id', 'estatus')
            ->get()
            ->groupBy('oficina_id');

        $officeRows = $offices->map(function (Oficina $office) use ($officeStatus) {
            $counts = $officeStatus->get($office->id, collect())->pluck('total', 'estatus');

            return [
                'office' => $office,
                'region' => (int) ($counts[Tarjeta::STATUS_ASIGNADA_OFICINA] ?? 0) + (int) ($counts[Tarjeta::STATUS_DEVUELTA] ?? 0),
                'capturistas' => (int) ($counts[Tarjeta::STATUS_ASIGNADA_USUARIO] ?? 0),
                'capturadas' => (int) ($counts[Tarjeta::STATUS_CONSUMIDA] ?? 0),
                'incidencias' => (int) ($counts[Tarjeta::STATUS_BLOQUEADA] ?? 0) + (int) ($counts[Tarjeta::STATUS_EXTRAVIADA] ?? 0),
                'total' => (int) $counts->sum(),
            ];
        });

        $municipioRows = (clone $baseCards)
            ->select([
                'oficina_id',
                'municipio_id',
            ])
            ->selectRaw('SUM(CASE WHEN estatus IN (?, ?) THEN 1 ELSE 0 END) as en_region', [Tarjeta::STATUS_ASIGNADA_OFICINA, Tarjeta::STATUS_DEVUELTA])
            ->selectRaw('SUM(CASE WHEN estatus = ? THEN 1 ELSE 0 END) as con_capturistas', [Tarjeta::STATUS_ASIGNADA_USUARIO])
            ->selectRaw('SUM(CASE WHEN estatus = ? THEN 1 ELSE 0 END) as capturadas', [Tarjeta::STATUS_CONSUMIDA])
            ->selectRaw('SUM(CASE WHEN estatus IN (?, ?) THEN 1 ELSE 0 END) as incidencias', [Tarjeta::STATUS_BLOQUEADA, Tarjeta::STATUS_EXTRAVIADA])
            ->selectRaw('COUNT(*) as total')
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
        $valesRoute = $isAdmin
            ? route('admin.inventario.vales.index')
            : route('delegacion.inventario.vales.index');
        $usersRoute = $isAdmin
            ? route('admin.usuarios.index')
            : route('delegacion.usuarios.index');

        return view('stack.index', compact(
            'isAdmin',
            'global',
            'officeRows',
            'municipioRows',
            'officesById',
            'municipiosById',
            'cardsRoute',
            'valesRoute',
            'usersRoute'
        ));
    }
}
