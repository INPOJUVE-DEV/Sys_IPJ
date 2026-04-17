<?php

namespace App\Http\Controllers\Delegacion;

use App\Http\Controllers\Controller;
use App\Models\Municipio;
use App\Models\Tarjeta;
use App\Services\TarjetaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InventarioTarjetaController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $filters = $request->only(['municipio_id']);

        $groups = Tarjeta::query()
            ->with('municipio:id,nombre')
            ->select([
                'municipio_id',
                DB::raw('COUNT(*) as asignadas'),
            ])
            ->selectRaw('SUM(CASE WHEN estatus = ? THEN 1 ELSE 0 END) as capturadas', [Tarjeta::STATUS_CONSUMIDA])
            ->accessibleTo($user)
            ->whereNotNull('municipio_id')
            ->when($filters['municipio_id'] ?? null, fn ($q, $value) => $q->where('municipio_id', $value))
            ->groupBy('municipio_id')
            ->orderBy('municipio_id')
            ->paginate(25)
            ->withQueryString();

        $municipios = Municipio::where('oficina_id', $user->oficina_id)
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'region']);
        $municipiosById = $municipios->keyBy('id');
        $summary = [
            'oficina' => Tarjeta::where('oficina_id', $user->oficina_id)->count(),
            'pendientes' => Tarjeta::where('oficina_id', $user->oficina_id)
                ->whereIn('estatus', [Tarjeta::STATUS_ASIGNADA_OFICINA, Tarjeta::STATUS_DEVUELTA])
                ->count(),
            'usuario' => Tarjeta::where('oficina_id', $user->oficina_id)
                ->whereNotNull('municipio_id')
                ->whereNotIn('estatus', [
                    Tarjeta::STATUS_CONSUMIDA,
                    Tarjeta::STATUS_BLOQUEADA,
                    Tarjeta::STATUS_EXTRAVIADA,
                ])
                ->count(),
            'consumida' => Tarjeta::where('oficina_id', $user->oficina_id)
                ->where('estatus', Tarjeta::STATUS_CONSUMIDA)
                ->count(),
            'incidencias' => Tarjeta::where('oficina_id', $user->oficina_id)
                ->whereIn('estatus', [Tarjeta::STATUS_BLOQUEADA, Tarjeta::STATUS_EXTRAVIADA])
                ->count(),
        ];

        return view('delegacion.inventario.tarjetas.index', compact('groups', 'municipios', 'municipiosById', 'filters', 'summary'));
    }

    public function assignRange(Request $request, TarjetaService $tarjetaService)
    {
        $data = $request->validate([
            'cantidad' => ['required', 'integer', 'min:1', 'max:50000'],
            'municipio_id' => ['required', 'exists:municipios,id'],
            'observaciones' => ['nullable', 'string'],
        ]);

        $count = $tarjetaService->assignQuantityToMunicipio(
            $request->user(),
            Municipio::findOrFail($data['municipio_id']),
            (int) $data['cantidad'],
            $data['observaciones'] ?? null,
        );

        return redirect()->route('delegacion.inventario.tarjetas.index')->with('status', "Tarjetas asignadas al municipio: {$count}");
    }

    public function updateStatus(Request $request, Tarjeta $tarjeta, TarjetaService $tarjetaService)
    {
        $data = $request->validate([
            'estatus' => ['required', 'string'],
            'observaciones' => ['nullable', 'string'],
        ]);

        $tarjetaService->markStatus($request->user(), $tarjeta, $data['estatus'], $data['observaciones'] ?? null);

        return redirect()->route('delegacion.inventario.tarjetas.index')->with('status', 'Estatus de tarjeta actualizado');
    }
}
