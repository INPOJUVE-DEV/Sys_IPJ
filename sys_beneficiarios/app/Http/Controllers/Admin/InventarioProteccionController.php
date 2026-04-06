<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Proteccion;
use App\Models\User;
use App\Services\ProteccionService;
use Illuminate\Http\Request;

class InventarioProteccionController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->only(['q', 'tipo', 'estatus', 'usuario_uuid']);

        $protecciones = Proteccion::with(['usuario', 'beneficiario'])
            ->when($filters['q'] ?? null, fn ($query, $value) => $query->where('numero_inventario', 'like', "%{$value}%"))
            ->when($filters['tipo'] ?? null, fn ($query, $value) => $query->where('tipo', $value))
            ->when($filters['estatus'] ?? null, fn ($query, $value) => $query->where('estatus', $value))
            ->when($filters['usuario_uuid'] ?? null, fn ($query, $value) => $query->where('usuario_uuid', $value))
            ->orderBy('numero_inventario')
            ->paginate(20)
            ->withQueryString();

        $users = User::role('skate_plaza')->orderBy('name')->get(['uuid', 'name']);
        $types = Proteccion::query()->select('tipo')->distinct()->orderBy('tipo')->pluck('tipo');
        $summary = [
            'total' => Proteccion::count(),
            'disponible' => Proteccion::where('estatus', Proteccion::STATUS_DISPONIBLE)->count(),
            'prestada' => Proteccion::where('estatus', Proteccion::STATUS_PRESTADA)->count(),
            'inactiva' => Proteccion::where('estatus', Proteccion::STATUS_INACTIVA)->count(),
        ];

        return view('admin.inventario.protecciones.index', compact('protecciones', 'users', 'types', 'summary', 'filters'));
    }

    public function storeBatch(Request $request, ProteccionService $proteccionService)
    {
        $data = $request->validate([
            'tipo' => ['required', 'string', 'max:255'],
            'usuario_uuid' => ['required', 'exists:users,uuid'],
            'numeros_inventario' => ['required', 'string'],
            'observaciones' => ['nullable', 'string'],
        ]);

        $numbers = preg_split('/[\r\n,]+/', $data['numeros_inventario']) ?: [];
        $destino = User::where('uuid', $data['usuario_uuid'])->firstOrFail();

        $count = $proteccionService->createBatch(
            $request->user(),
            $destino,
            $data['tipo'],
            $numbers,
            $data['observaciones'] ?? null,
        );

        return redirect()->route('admin.inventario.protecciones.index')->with('status', "Protecciones creadas: {$count}");
    }

    public function transfer(Request $request, Proteccion $proteccion, ProteccionService $proteccionService)
    {
        $data = $request->validate([
            'usuario_uuid' => ['required', 'exists:users,uuid'],
            'observaciones' => ['nullable', 'string'],
        ]);

        $destino = User::where('uuid', $data['usuario_uuid'])->firstOrFail();
        $proteccionService->transferToUser($request->user(), $proteccion, $destino, $data['observaciones'] ?? null);

        return redirect()->route('admin.inventario.protecciones.index')->with('status', 'Proteccion transferida correctamente');
    }

    public function updateStatus(Request $request, Proteccion $proteccion, ProteccionService $proteccionService)
    {
        $data = $request->validate([
            'estatus' => ['required', 'string'],
            'observaciones' => ['nullable', 'string'],
        ]);

        $proteccionService->changeAvailability(
            $request->user(),
            $proteccion,
            $data['estatus'],
            $data['observaciones'] ?? null,
        );

        return redirect()->route('admin.inventario.protecciones.index')->with('status', 'Estatus de proteccion actualizado');
    }
}
