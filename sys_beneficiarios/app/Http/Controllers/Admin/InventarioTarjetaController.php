<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Beneficiario;
use App\Models\Municipio;
use App\Models\Oficina;
use App\Models\Tarjeta;
use App\Services\TarjetaService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InventarioTarjetaController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->only(['oficina_id', 'municipio_id']);
        $offices = Oficina::where('activo', true)->orderBy('nombre')->get(['id', 'nombre', 'tipo', 'region']);
        $delegaciones = $offices
            ->where('tipo', Oficina::TIPO_DELEGACION)
            ->values();
        $centralOfficeId = $offices->firstWhere('tipo', Oficina::TIPO_CENTRAL)?->id;
        $delegacionIds = $delegaciones
            ->pluck('id')
            ->all();

        $summary = [
            'total' => Tarjeta::count(),
            'central' => $centralOfficeId ? Tarjeta::where('oficina_id', $centralOfficeId)->count() : 0,
            'delegacion' => Tarjeta::whereIn('oficina_id', $delegacionIds)
                ->whereNull('municipio_id')
                ->count(),
            'municipio' => Tarjeta::whereNotNull('municipio_id')->count(),
            'beneficiarios' => Beneficiario::count(),
        ];

        $municipiosConTarjetasIds = Tarjeta::query()
            ->whereNotNull('municipio_id')
            ->when($filters['oficina_id'] ?? null, fn ($query, $value) => $query->where('oficina_id', $value))
            ->distinct()
            ->pluck('municipio_id');

        $municipios = Municipio::query()
            ->whereIn('id', $municipiosConTarjetasIds)
            ->orderBy('region')
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'region', 'oficina_id']);

        $selectedMunicipio = ($filters['municipio_id'] ?? null)
            ? $municipios->firstWhere('id', (int) $filters['municipio_id'])
            : null;
        $dashboardOfficeIds = $delegaciones
            ->when($filters['oficina_id'] ?? null, fn ($collection, $value) => $collection->where('id', (int) $value))
            ->when(
                ! ($filters['oficina_id'] ?? null) && $selectedMunicipio,
                fn ($collection) => $collection->where('id', $selectedMunicipio->oficina_id)
            )
            ->pluck('id')
            ->values();

        if ($dashboardOfficeIds->isEmpty()) {
            $dashboardOfficeIds = $delegaciones->pluck('id')->values();
        }

        $dashboardMunicipios = $municipios
            ->whereIn('oficina_id', $dashboardOfficeIds->all())
            ->when($filters['municipio_id'] ?? null, fn ($collection, $value) => $collection->where('id', (int) $value))
            ->values();

        $officeAssignedCounts = Tarjeta::query()
            ->whereIn('oficina_id', $dashboardOfficeIds->all())
            ->selectRaw('oficina_id, COUNT(*) as total')
            ->groupBy('oficina_id')
            ->pluck('total', 'oficina_id');

        $officeCapturedCounts = Beneficiario::query()
            ->join('municipios', 'municipios.id', '=', 'beneficiarios.municipio_id')
            ->whereIn('municipios.oficina_id', $dashboardOfficeIds->all())
            ->selectRaw('municipios.oficina_id, COUNT(*) as total')
            ->groupBy('municipios.oficina_id')
            ->pluck('total', 'municipios.oficina_id');

        $municipioAssignedCounts = Tarjeta::query()
            ->when(
                $dashboardMunicipios->isNotEmpty(),
                fn ($query) => $query->whereIn('municipio_id', $dashboardMunicipios->pluck('id')->all()),
                fn ($query) => $query->whereRaw('1 = 0')
            )
            ->selectRaw('municipio_id, COUNT(*) as total')
            ->groupBy('municipio_id')
            ->pluck('total', 'municipio_id');

        $municipioCapturedCounts = Beneficiario::query()
            ->when(
                $dashboardMunicipios->isNotEmpty(),
                fn ($query) => $query->whereIn('municipio_id', $dashboardMunicipios->pluck('id')->all()),
                fn ($query) => $query->whereRaw('1 = 0')
            )
            ->selectRaw('municipio_id, COUNT(*) as total')
            ->groupBy('municipio_id')
            ->pluck('total', 'municipio_id');

        $municipiosPorDelegacion = $dashboardMunicipios
            ->map(function ($municipio) use ($municipioAssignedCounts, $municipioCapturedCounts) {
                $asignadas = (int) ($municipioAssignedCounts[$municipio->id] ?? 0);
                $capturadas = (int) ($municipioCapturedCounts[$municipio->id] ?? 0);

                return (object) [
                    'id' => $municipio->id,
                    'nombre' => $municipio->nombre,
                    'region' => $municipio->region,
                    'oficina_id' => $municipio->oficina_id,
                    'asignadas' => $asignadas,
                    'capturadas' => $capturadas,
                    'pendientes' => max($asignadas - $capturadas, 0),
                ];
            })
            ->filter(fn ($municipio) => $municipio->asignadas > 0 || $municipio->capturadas > 0 || ($filters['municipio_id'] ?? null))
            ->groupBy('oficina_id');

        $officeDashboards = $delegaciones
            ->whereIn('id', $dashboardOfficeIds->all())
            ->map(function ($office) use ($officeAssignedCounts, $officeCapturedCounts, $municipiosPorDelegacion) {
                $asignadas = (int) ($officeAssignedCounts[$office->id] ?? 0);
                $capturadas = (int) ($officeCapturedCounts[$office->id] ?? 0);

                return (object) [
                    'id' => $office->id,
                    'nombre' => $office->nombre,
                    'region' => $office->region,
                    'asignadas' => $asignadas,
                    'capturadas' => $capturadas,
                    'pendientes' => max($asignadas - $capturadas, 0),
                    'municipios' => $municipiosPorDelegacion->get($office->id, collect())->values(),
                ];
            })
            ->filter(fn ($office) => $office->asignadas > 0 || $office->capturadas > 0 || $office->municipios->isNotEmpty());

        return view('admin.inventario.tarjetas.index', compact(
            'offices',
            'delegaciones',
            'municipios',
            'officeDashboards',
            'summary',
            'filters'
        ));
    }

    public function storeRange(Request $request, TarjetaService $tarjetaService)
    {
        $data = $request->validate([
            'cantidad' => ['required', 'integer', 'min:1', 'max:50000'],
            'observaciones' => ['nullable', 'string'],
        ]);

        $central = Oficina::where('tipo', Oficina::TIPO_CENTRAL)->firstOrFail();

        $count = $tarjetaService->createQuantity(
            $request->user(),
            $central,
            (int) $data['cantidad'],
            null,
            $data['observaciones'] ?? null,
        );

        return redirect()->route('admin.inventario.tarjetas.index')->with('status', "Tarjetas agregadas: {$count}");
    }

    public function transferRange(Request $request, TarjetaService $tarjetaService)
    {
        $data = $request->validate([
            'cantidad' => ['required', 'integer', 'min:1', 'max:50000'],
            'destino_oficina_id' => [
                'required',
                Rule::exists('oficinas', 'id')->where(fn ($query) => $query->where('tipo', Oficina::TIPO_DELEGACION)),
            ],
            'observaciones' => ['nullable', 'string'],
        ]);

        $origen = Oficina::where('tipo', Oficina::TIPO_CENTRAL)->firstOrFail();

        $count = $tarjetaService->transferQuantity(
            $request->user(),
            Oficina::findOrFail($data['destino_oficina_id']),
            (int) $data['cantidad'],
            null,
            $origen,
            $data['observaciones'] ?? null,
        );

        return redirect()->route('admin.inventario.tarjetas.index')->with('status', "Tarjetas movidas: {$count}");
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

        return redirect()->route('admin.inventario.tarjetas.index')->with('status', "Tarjetas asignadas al municipio: {$count}");
    }

    public function updateStatus(Request $request, Tarjeta $tarjeta, TarjetaService $tarjetaService)
    {
        $data = $request->validate([
            'estatus' => ['required', 'string'],
            'observaciones' => ['nullable', 'string'],
        ]);

        $tarjetaService->markStatus($request->user(), $tarjeta, $data['estatus'], $data['observaciones'] ?? null);

        return redirect()->route('admin.inventario.tarjetas.index')->with('status', 'Estatus de tarjeta actualizado');
    }
}
