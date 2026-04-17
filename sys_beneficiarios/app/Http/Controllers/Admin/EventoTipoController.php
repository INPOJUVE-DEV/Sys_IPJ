<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEventoTipoRequest;
use App\Http\Requests\UpdateEventoTipoRequest;
use App\Models\EventoTipo;
use Illuminate\Http\Request;

class EventoTipoController extends Controller
{
    public function index(Request $request)
    {
        $q = $request->get('q');
        $activo = $request->get('activo');

        $tipos = EventoTipo::withCount('eventos')
            ->when($q, fn ($query) => $query->where('nombre', 'like', "%$q%"))
            ->when($activo !== null && $activo !== '', fn ($query) => $query->where('activo', (bool) $activo))
            ->orderBy('nombre')
            ->paginate(15)
            ->withQueryString();

        return view('admin.evento_tipos.index', compact('tipos', 'q', 'activo'));
    }

    public function create()
    {
        return view('admin.evento_tipos.create');
    }

    public function store(StoreEventoTipoRequest $request)
    {
        EventoTipo::create($request->validated());

        return redirect()->route('admin.evento-tipos.index')->with('status', 'Tipo de evento creado correctamente');
    }

    public function edit(EventoTipo $eventoTipo)
    {
        return view('admin.evento_tipos.edit', compact('eventoTipo'));
    }

    public function update(UpdateEventoTipoRequest $request, EventoTipo $eventoTipo)
    {
        $eventoTipo->update($request->validated());

        return redirect()->route('admin.evento-tipos.index')->with('status', 'Tipo de evento actualizado correctamente');
    }

    public function destroy(EventoTipo $eventoTipo)
    {
        if ($eventoTipo->eventos()->exists()) {
            return redirect()
                ->route('admin.evento-tipos.index')
                ->with('error', 'No se puede eliminar un tipo con eventos capturados. Puedes desactivarlo.');
        }

        $eventoTipo->delete();

        return redirect()->route('admin.evento-tipos.index')->with('status', 'Tipo de evento eliminado');
    }
}
