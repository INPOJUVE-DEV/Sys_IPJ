<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Municipio;
use App\Models\Oficina;
use App\Models\Tarjeta;
use App\Models\User;
use App\Services\TarjetaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InventarioTarjetaController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->only(['oficina_id', 'municipio_id', 'estatus', 'usuario_uuid']);

        $query = Tarjeta::query()
            ->select([
                'oficina_id',
                'municipio_id',
                'usuario_uuid',
                'estatus',
                DB::raw('COUNT(*) as total'),
            ])
            ->when($filters['oficina_id'] ?? null, fn ($q, $value) => $q->where('oficina_id', $value))
            ->when($filters['municipio_id'] ?? null, fn ($q, $value) => $q->where('municipio_id', $value))
            ->when($filters['estatus'] ?? null, fn ($q, $value) => $q->where('estatus', $value))
            ->when($filters['usuario_uuid'] ?? null, fn ($q, $value) => $q->where('usuario_uuid', $value))
            ->groupBy('oficina_id', 'municipio_id', 'usuario_uuid', 'estatus')
            ->orderBy('oficina_id')
            ->orderBy('municipio_id')
            ->orderBy('estatus');

        $groups = $query->paginate(25)->withQueryString();
        $offices = Oficina::where('activo', true)->orderBy('nombre')->get(['id', 'nombre', 'tipo']);
        $officesById = $offices->keyBy('id');
        $municipios = Municipio::orderBy('region')->orderBy('nombre')->get(['id', 'nombre', 'region', 'oficina_id']);
        $municipiosById = $municipios->keyBy('id');
        $users = User::role(['capturista', 'capturista_programas'])->whereNotNull('oficina_id')->orderBy('name')->get(['uuid', 'name', 'oficina_id']);
        $usersByUuid = $users->keyBy('uuid');

        $summary = [
            'total' => Tarjeta::count(),
            'disponible' => Tarjeta::where('estatus', Tarjeta::STATUS_DISPONIBLE)->count(),
            'oficina' => Tarjeta::where('estatus', Tarjeta::STATUS_ASIGNADA_OFICINA)->count(),
            'usuario' => Tarjeta::where('estatus', Tarjeta::STATUS_ASIGNADA_USUARIO)->count(),
            'consumida' => Tarjeta::where('estatus', Tarjeta::STATUS_CONSUMIDA)->count(),
            'incidencias' => Tarjeta::whereIn('estatus', [Tarjeta::STATUS_BLOQUEADA, Tarjeta::STATUS_EXTRAVIADA])->count(),
        ];

        return view('admin.inventario.tarjetas.index', compact(
            'groups',
            'offices',
            'officesById',
            'municipios',
            'municipiosById',
            'users',
            'usersByUuid',
            'summary',
            'filters'
        ));
    }

    public function storeRange(Request $request, TarjetaService $tarjetaService)
    {
        $data = $request->validate([
            'cantidad' => ['required', 'integer', 'min:1', 'max:50000'],
            'oficina_id' => ['required', 'exists:oficinas,id'],
            'municipio_id' => ['nullable', 'exists:municipios,id'],
            'observaciones' => ['nullable', 'string'],
        ]);

        $count = $tarjetaService->createQuantity(
            $request->user(),
            Oficina::findOrFail($data['oficina_id']),
            (int) $data['cantidad'],
            isset($data['municipio_id']) ? Municipio::find($data['municipio_id']) : null,
            $data['observaciones'] ?? null,
        );

        return redirect()->route('admin.inventario.tarjetas.index')->with('status', "Tarjetas agregadas: {$count}");
    }

    public function transferRange(Request $request, TarjetaService $tarjetaService)
    {
        $data = $request->validate([
            'cantidad' => ['required', 'integer', 'min:1', 'max:50000'],
            'origen_oficina_id' => ['nullable', 'exists:oficinas,id'],
            'destino_oficina_id' => ['required', 'exists:oficinas,id'],
            'municipio_id' => ['nullable', 'exists:municipios,id'],
            'observaciones' => ['nullable', 'string'],
        ]);

        $origen = isset($data['origen_oficina_id'])
            ? Oficina::find($data['origen_oficina_id'])
            : Oficina::where('tipo', Oficina::TIPO_CENTRAL)->first();

        $count = $tarjetaService->transferQuantity(
            $request->user(),
            Oficina::findOrFail($data['destino_oficina_id']),
            (int) $data['cantidad'],
            isset($data['municipio_id']) ? Municipio::find($data['municipio_id']) : null,
            $origen,
            $data['observaciones'] ?? null,
        );

        return redirect()->route('admin.inventario.tarjetas.index')->with('status', "Tarjetas movidas: {$count}");
    }

    public function assignRange(Request $request, TarjetaService $tarjetaService)
    {
        $data = $request->validate([
            'cantidad' => ['required', 'integer', 'min:1', 'max:50000'],
            'usuario_uuid' => ['required', 'exists:users,uuid'],
            'municipio_id' => ['nullable', 'exists:municipios,id'],
            'observaciones' => ['nullable', 'string'],
        ]);

        $count = $tarjetaService->assignQuantityToUser(
            $request->user(),
            User::where('uuid', $data['usuario_uuid'])->firstOrFail(),
            (int) $data['cantidad'],
            isset($data['municipio_id']) ? Municipio::find($data['municipio_id']) : null,
            $data['observaciones'] ?? null,
        );

        return redirect()->route('admin.inventario.tarjetas.index')->with('status', "Tarjetas entregadas: {$count}");
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
