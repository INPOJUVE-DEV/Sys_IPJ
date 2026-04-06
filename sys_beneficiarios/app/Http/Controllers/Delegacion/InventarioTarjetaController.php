<?php

namespace App\Http\Controllers\Delegacion;

use App\Http\Controllers\Controller;
use App\Models\Tarjeta;
use App\Models\User;
use App\Services\TarjetaService;
use Illuminate\Http\Request;

class InventarioTarjetaController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $filters = $request->only(['q', 'estatus', 'usuario_uuid']);

        $tarjetas = Tarjeta::with(['usuario', 'beneficiario'])
            ->accessibleTo($user)
            ->when($filters['q'] ?? null, fn ($q, $value) => $q->where('folio', 'like', "%{$value}%"))
            ->when($filters['estatus'] ?? null, fn ($q, $value) => $q->where('estatus', $value))
            ->when($filters['usuario_uuid'] ?? null, fn ($q, $value) => $q->where('usuario_uuid', $value))
            ->orderBy('folio')
            ->paginate(20)
            ->withQueryString();

        $capturistas = User::role('capturista')
            ->where('oficina_id', $user->oficina_id)
            ->orderBy('name')
            ->get(['uuid', 'name']);
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

        return view('delegacion.inventario.tarjetas.index', compact('tarjetas', 'capturistas', 'filters', 'summary'));
    }

    public function assignRange(Request $request, TarjetaService $tarjetaService)
    {
        $data = $request->validate([
            'prefijo' => ['nullable', 'string', 'max:50'],
            'folio_desde' => ['required', 'integer', 'min:0'],
            'folio_hasta' => ['required', 'integer', 'min:0'],
            'padding' => ['nullable', 'integer', 'min:0', 'max:12'],
            'usuario_uuid' => ['required', 'exists:users,uuid'],
            'observaciones' => ['nullable', 'string'],
        ]);

        $destino = User::where('uuid', $data['usuario_uuid'])
            ->where('oficina_id', $request->user()->oficina_id)
            ->firstOrFail();

        $count = $tarjetaService->assignRangeToUser(
            $request->user(),
            $destino,
            $data['prefijo'] ?? '',
            (int) $data['folio_desde'],
            (int) $data['folio_hasta'],
            (int) ($data['padding'] ?? 0),
            $data['observaciones'] ?? null,
        );

        return redirect()->route('delegacion.inventario.tarjetas.index')->with('status', "Tarjetas asignadas: {$count}");
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
