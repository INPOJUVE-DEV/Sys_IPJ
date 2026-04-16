<?php

namespace App\Http\Controllers\Delegacion;

use App\Http\Controllers\Controller;
use App\Models\Municipio;
use App\Models\Tarjeta;
use App\Models\User;
use App\Services\TarjetaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InventarioTarjetaController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $filters = $request->only(['estatus', 'usuario_uuid', 'municipio_id']);

        $groups = Tarjeta::query()
            ->select([
                'oficina_id',
                'municipio_id',
                'usuario_uuid',
                'estatus',
                DB::raw('COUNT(*) as total'),
            ])
            ->accessibleTo($user)
            ->when($filters['estatus'] ?? null, fn ($q, $value) => $q->where('estatus', $value))
            ->when($filters['usuario_uuid'] ?? null, fn ($q, $value) => $q->where('usuario_uuid', $value))
            ->when($filters['municipio_id'] ?? null, fn ($q, $value) => $q->where('municipio_id', $value))
            ->groupBy('oficina_id', 'municipio_id', 'usuario_uuid', 'estatus')
            ->orderBy('municipio_id')
            ->orderBy('estatus')
            ->paginate(25)
            ->withQueryString();

        $capturistas = User::role(['capturista', 'capturista_programas'])
            ->where('oficina_id', $user->oficina_id)
            ->orderBy('name')
            ->get(['uuid', 'name']);
        $capturistasByUuid = $capturistas->keyBy('uuid');
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
                ->where('estatus', Tarjeta::STATUS_ASIGNADA_USUARIO)
                ->count(),
            'consumida' => Tarjeta::where('oficina_id', $user->oficina_id)
                ->where('estatus', Tarjeta::STATUS_CONSUMIDA)
                ->count(),
            'incidencias' => Tarjeta::where('oficina_id', $user->oficina_id)
                ->whereIn('estatus', [Tarjeta::STATUS_BLOQUEADA, Tarjeta::STATUS_EXTRAVIADA])
                ->count(),
        ];

        return view('delegacion.inventario.tarjetas.index', compact('groups', 'capturistas', 'capturistasByUuid', 'municipios', 'municipiosById', 'filters', 'summary'));
    }

    public function assignRange(Request $request, TarjetaService $tarjetaService)
    {
        $data = $request->validate([
            'cantidad' => ['required', 'integer', 'min:1', 'max:50000'],
            'usuario_uuid' => ['required', 'exists:users,uuid'],
            'municipio_id' => ['nullable', 'exists:municipios,id'],
            'observaciones' => ['nullable', 'string'],
        ]);

        $destino = User::where('uuid', $data['usuario_uuid'])
            ->where('oficina_id', $request->user()->oficina_id)
            ->firstOrFail();

        $municipio = isset($data['municipio_id'])
            ? Municipio::where('oficina_id', $request->user()->oficina_id)->where('id', $data['municipio_id'])->firstOrFail()
            : null;

        $count = $tarjetaService->assignQuantityToUser(
            $request->user(),
            $destino,
            (int) $data['cantidad'],
            $municipio,
            $data['observaciones'] ?? null,
        );

        return redirect()->route('delegacion.inventario.tarjetas.index')->with('status', "Tarjetas entregadas: {$count}");
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
