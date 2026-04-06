<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Oficina;
use App\Models\User;
use App\Models\ValeBloc;
use App\Services\ValeBlocService;
use Illuminate\Http\Request;

class InventarioValeController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->only(['oficina_id', 'estatus', 'usuario_uuid']);

        $valeBlocs = ValeBloc::with(['oficina', 'usuario'])
            ->when($filters['oficina_id'] ?? null, fn ($q, $value) => $q->where('oficina_id', $value))
            ->when($filters['estatus'] ?? null, fn ($q, $value) => $q->where('estatus', $value))
            ->when($filters['usuario_uuid'] ?? null, fn ($q, $value) => $q->where('usuario_uuid', $value))
            ->orderByDesc('folio_inicio')
            ->paginate(20)
            ->withQueryString();

        $offices = Oficina::where('activo', true)->orderBy('nombre')->get(['id', 'nombre', 'tipo']);
        $users = User::role(['delegado', 'capturista'])->whereNotNull('oficina_id')->orderBy('name')->get(['uuid', 'name', 'oficina_id']);
        $summary = [
            'total' => ValeBloc::count(),
            'oficina' => ValeBloc::where('estatus', ValeBloc::STATUS_ASIGNADO_OFICINA)->count(),
            'usuario' => ValeBloc::where('estatus', ValeBloc::STATUS_ASIGNADO_USUARIO)->count(),
            'cerrado' => ValeBloc::where('estatus', ValeBloc::STATUS_CERRADO)->count(),
            'incidencias' => ValeBloc::whereIn('estatus', [ValeBloc::STATUS_BLOQUEADO, ValeBloc::STATUS_EXTRAVIADO])->count(),
        ];

        return view('admin.inventario.vales.index', compact('valeBlocs', 'offices', 'users', 'filters', 'summary'));
    }

    public function store(Request $request, ValeBlocService $valeBlocService)
    {
        $data = $request->validate([
            'folio_inicio' => ['required', 'integer', 'min:0'],
            'folio_fin' => ['required', 'integer', 'min:0'],
            'oficina_id' => ['required', 'exists:oficinas,id'],
            'observaciones' => ['nullable', 'string'],
        ]);

        $valeBlocService->createBlock(
            $request->user(),
            Oficina::findOrFail($data['oficina_id']),
            (int) $data['folio_inicio'],
            (int) $data['folio_fin'],
            $data['observaciones'] ?? null,
        );

        return redirect()->route('admin.inventario.vales.index')->with('status', 'Bloc de vales creado correctamente');
    }

    public function transfer(Request $request, ValeBloc $valeBloc, ValeBlocService $valeBlocService)
    {
        $data = $request->validate([
            'destino_oficina_id' => ['required', 'exists:oficinas,id'],
            'observaciones' => ['nullable', 'string'],
        ]);

        $valeBlocService->transfer(
            $request->user(),
            $valeBloc,
            Oficina::findOrFail($data['destino_oficina_id']),
            $data['observaciones'] ?? null,
        );

        return redirect()->route('admin.inventario.vales.index')->with('status', 'Bloc transferido correctamente');
    }

    public function assign(Request $request, ValeBloc $valeBloc, ValeBlocService $valeBlocService)
    {
        $data = $request->validate([
            'usuario_uuid' => ['required', 'exists:users,uuid'],
            'observaciones' => ['nullable', 'string'],
        ]);

        $valeBlocService->assignToUser(
            $request->user(),
            $valeBloc,
            User::where('uuid', $data['usuario_uuid'])->firstOrFail(),
            $data['observaciones'] ?? null,
        );

        return redirect()->route('admin.inventario.vales.index')->with('status', 'Bloc asignado correctamente');
    }

    public function updateStatus(Request $request, ValeBloc $valeBloc, ValeBlocService $valeBlocService)
    {
        $data = $request->validate([
            'estatus' => ['required', 'string'],
            'observaciones' => ['nullable', 'string'],
        ]);

        $valeBlocService->markStatus(
            $request->user(),
            $valeBloc,
            $data['estatus'],
            $data['observaciones'] ?? null,
        );

        return redirect()->route('admin.inventario.vales.index')->with('status', 'Estatus de bloc actualizado');
    }
}
