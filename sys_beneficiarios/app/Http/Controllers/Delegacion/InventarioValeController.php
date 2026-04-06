<?php

namespace App\Http\Controllers\Delegacion;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\ValeBloc;
use App\Services\ValeBlocService;
use Illuminate\Http\Request;

class InventarioValeController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $filters = $request->only(['estatus', 'usuario_uuid']);

        $valeBlocs = ValeBloc::with('usuario')
            ->accessibleTo($user)
            ->when($filters['estatus'] ?? null, fn ($q, $value) => $q->where('estatus', $value))
            ->when($filters['usuario_uuid'] ?? null, fn ($q, $value) => $q->where('usuario_uuid', $value))
            ->orderByDesc('folio_inicio')
            ->paginate(20)
            ->withQueryString();

        $capturistas = User::role('capturista')
            ->where('oficina_id', $user->oficina_id)
            ->orderBy('name')
            ->get(['uuid', 'name']);
        $summary = [
            'total' => ValeBloc::where('oficina_id', $user->oficina_id)->count(),
            'oficina' => ValeBloc::where('oficina_id', $user->oficina_id)
                ->where('estatus', ValeBloc::STATUS_ASIGNADO_OFICINA)
                ->count(),
            'usuario' => ValeBloc::where('oficina_id', $user->oficina_id)
                ->where('estatus', ValeBloc::STATUS_ASIGNADO_USUARIO)
                ->count(),
            'cerrado' => ValeBloc::where('oficina_id', $user->oficina_id)
                ->where('estatus', ValeBloc::STATUS_CERRADO)
                ->count(),
            'incidencias' => ValeBloc::where('oficina_id', $user->oficina_id)
                ->whereIn('estatus', [ValeBloc::STATUS_BLOQUEADO, ValeBloc::STATUS_EXTRAVIADO])
                ->count(),
        ];

        return view('delegacion.inventario.vales.index', compact('valeBlocs', 'capturistas', 'filters', 'summary'));
    }

    public function assign(Request $request, ValeBloc $valeBloc, ValeBlocService $valeBlocService)
    {
        $data = $request->validate([
            'usuario_uuid' => ['required', 'exists:users,uuid'],
            'observaciones' => ['nullable', 'string'],
        ]);

        $destino = User::where('uuid', $data['usuario_uuid'])
            ->where('oficina_id', $request->user()->oficina_id)
            ->firstOrFail();

        $valeBlocService->assignToUser(
            $request->user(),
            $valeBloc,
            $destino,
            $data['observaciones'] ?? null,
        );

        return redirect()->route('delegacion.inventario.vales.index')->with('status', 'Bloc asignado correctamente');
    }

    public function updateStatus(Request $request, ValeBloc $valeBloc, ValeBlocService $valeBlocService)
    {
        $data = $request->validate([
            'estatus' => ['required', 'string'],
            'observaciones' => ['nullable', 'string'],
        ]);

        $valeBlocService->markStatus($request->user(), $valeBloc, $data['estatus'], $data['observaciones'] ?? null);

        return redirect()->route('delegacion.inventario.vales.index')->with('status', 'Estatus de bloc actualizado');
    }
}
