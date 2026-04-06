<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Oficina;
use App\Models\Tarjeta;
use App\Models\User;
use App\Services\TarjetaService;
use Illuminate\Http\Request;

class InventarioTarjetaController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->only(['q', 'oficina_id', 'estatus', 'usuario_uuid']);

        $query = Tarjeta::with(['oficina', 'usuario', 'beneficiario'])
            ->when($filters['q'] ?? null, fn ($q, $value) => $q->where('folio', 'like', "%{$value}%"))
            ->when($filters['oficina_id'] ?? null, fn ($q, $value) => $q->where('oficina_id', $value))
            ->when($filters['estatus'] ?? null, fn ($q, $value) => $q->where('estatus', $value))
            ->when($filters['usuario_uuid'] ?? null, fn ($q, $value) => $q->where('usuario_uuid', $value))
            ->orderBy('folio');

        $tarjetas = $query->paginate(20)->withQueryString();
        $offices = Oficina::where('activo', true)->orderBy('nombre')->get(['id', 'nombre', 'tipo']);
        $users = User::role(['delegado', 'capturista'])->whereNotNull('oficina_id')->orderBy('name')->get(['uuid', 'name', 'oficina_id']);

        $summary = [
            'total' => Tarjeta::count(),
            'disponible' => Tarjeta::where('estatus', Tarjeta::STATUS_DISPONIBLE)->count(),
            'oficina' => Tarjeta::where('estatus', Tarjeta::STATUS_ASIGNADA_OFICINA)->count(),
            'usuario' => Tarjeta::where('estatus', Tarjeta::STATUS_ASIGNADA_USUARIO)->count(),
            'consumida' => Tarjeta::where('estatus', Tarjeta::STATUS_CONSUMIDA)->count(),
            'incidencias' => Tarjeta::whereIn('estatus', [Tarjeta::STATUS_BLOQUEADA, Tarjeta::STATUS_EXTRAVIADA])->count(),
        ];

        return view('admin.inventario.tarjetas.index', compact('tarjetas', 'offices', 'users', 'summary', 'filters'));
    }

    public function storeRange(Request $request, TarjetaService $tarjetaService)
    {
        $data = $request->validate([
            'prefijo' => ['nullable', 'string', 'max:50'],
            'folio_desde' => ['required', 'integer', 'min:0'],
            'folio_hasta' => ['required', 'integer', 'min:0'],
            'padding' => ['nullable', 'integer', 'min:0', 'max:12'],
            'oficina_id' => ['required', 'exists:oficinas,id'],
            'observaciones' => ['nullable', 'string'],
        ]);

        $count = $tarjetaService->createRange(
            $request->user(),
            Oficina::findOrFail($data['oficina_id']),
            $data['prefijo'] ?? '',
            (int) $data['folio_desde'],
            (int) $data['folio_hasta'],
            (int) ($data['padding'] ?? 0),
            $data['observaciones'] ?? null,
        );

        return redirect()->route('admin.inventario.tarjetas.index')->with('status', "Tarjetas creadas: {$count}");
    }

    public function transferRange(Request $request, TarjetaService $tarjetaService)
    {
        $data = $request->validate([
            'prefijo' => ['nullable', 'string', 'max:50'],
            'folio_desde' => ['required', 'integer', 'min:0'],
            'folio_hasta' => ['required', 'integer', 'min:0'],
            'padding' => ['nullable', 'integer', 'min:0', 'max:12'],
            'destino_oficina_id' => ['required', 'exists:oficinas,id'],
            'observaciones' => ['nullable', 'string'],
        ]);

        $count = $tarjetaService->transferRange(
            $request->user(),
            Oficina::findOrFail($data['destino_oficina_id']),
            $data['prefijo'] ?? '',
            (int) $data['folio_desde'],
            (int) $data['folio_hasta'],
            (int) ($data['padding'] ?? 0),
            $data['observaciones'] ?? null,
        );

        return redirect()->route('admin.inventario.tarjetas.index')->with('status', "Tarjetas transferidas: {$count}");
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

        $count = $tarjetaService->assignRangeToUser(
            $request->user(),
            User::where('uuid', $data['usuario_uuid'])->firstOrFail(),
            $data['prefijo'] ?? '',
            (int) $data['folio_desde'],
            (int) $data['folio_hasta'],
            (int) ($data['padding'] ?? 0),
            $data['observaciones'] ?? null,
        );

        return redirect()->route('admin.inventario.tarjetas.index')->with('status', "Tarjetas asignadas: {$count}");
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
