<?php

namespace App\Http\Controllers\Delegacion;

use App\Http\Controllers\Controller;
use App\Models\Beneficiario;
use App\Models\Municipio;
use App\Models\Oficina;
use App\Models\Tarjeta;
use App\Services\TarjetaService;
use Illuminate\Http\Request;

class InventarioTarjetaController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $filters = $request->only(['municipio_id']);
        $office = Oficina::findOrFail($user->oficina_id);
        $municipioIds = Tarjeta::query()
            ->where('oficina_id', $user->oficina_id)
            ->whereNotNull('municipio_id')
            ->distinct()
            ->pluck('municipio_id');

        $municipios = Municipio::whereIn('id', $municipioIds)
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'region']);
        $dashboardMunicipios = $municipios
            ->when($filters['municipio_id'] ?? null, fn ($collection, $value) => $collection->where('id', (int) $value))
            ->values();

        $capturadasPorMunicipio = Beneficiario::query()
            ->when(
                $dashboardMunicipios->isNotEmpty(),
                fn ($query) => $query->whereIn('municipio_id', $dashboardMunicipios->pluck('id')->all()),
                fn ($query) => $query->whereRaw('1 = 0')
            )
            ->selectRaw('municipio_id, COUNT(*) as total')
            ->groupBy('municipio_id')
            ->pluck('total', 'municipio_id');

        $asignadasPorMunicipio = Tarjeta::query()
            ->where('oficina_id', $user->oficina_id)
            ->when(
                $dashboardMunicipios->isNotEmpty(),
                fn ($query) => $query->whereIn('municipio_id', $dashboardMunicipios->pluck('id')->all()),
                fn ($query) => $query->whereRaw('1 = 0')
            )
            ->selectRaw('municipio_id, COUNT(*) as total')
            ->groupBy('municipio_id')
            ->pluck('total', 'municipio_id');

        $municipioDashboard = $dashboardMunicipios
            ->map(function ($municipio) use ($asignadasPorMunicipio, $capturadasPorMunicipio) {
                $asignadas = (int) ($asignadasPorMunicipio[$municipio->id] ?? 0);
                $capturadas = (int) ($capturadasPorMunicipio[$municipio->id] ?? 0);

                return (object) [
                    'id' => $municipio->id,
                    'nombre' => $municipio->nombre,
                    'region' => $municipio->region,
                    'asignadas' => $asignadas,
                    'capturadas' => $capturadas,
                    'pendientes' => max($asignadas - $capturadas, 0),
                ];
            })
            ->filter(fn ($municipio) => $municipio->asignadas > 0 || $municipio->capturadas > 0 || ($filters['municipio_id'] ?? null))
            ->values();

        $summary = [
            'oficina' => Tarjeta::where('oficina_id', $user->oficina_id)->count(),
            'pendientes' => Tarjeta::where('oficina_id', $user->oficina_id)
                ->whereNull('municipio_id')
                ->count(),
            'usuario' => Tarjeta::where('oficina_id', $user->oficina_id)
                ->whereNotNull('municipio_id')
                ->count(),
            'consumida' => Beneficiario::query()
                ->when(
                    $municipioIds->isNotEmpty(),
                    fn ($query) => $query->whereIn('municipio_id', $municipioIds),
                    fn ($query) => $query->whereRaw('1 = 0')
                )
                ->count(),
        ];

        $officeDashboard = (object) [
            'id' => $office->id,
            'nombre' => $office->nombre,
            'region' => $office->region,
            'asignadas' => (int) $summary['oficina'],
            'capturadas' => (int) $summary['consumida'],
            'pendientes' => max((int) $summary['oficina'] - (int) $summary['consumida'], 0),
            'municipios' => $municipioDashboard,
        ];

        return view('delegacion.inventario.tarjetas.index', compact('municipios', 'filters', 'summary', 'officeDashboard'));
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
