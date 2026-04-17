<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEventoRequest;
use App\Http\Requests\UpdateEventoRequest;
use App\Models\Evento;
use App\Models\EventoTipo;
use App\Models\Municipio;
use Illuminate\Http\Request;

class EventoController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', Evento::class);

        $q = $request->get('q');
        $filters = $request->only(['evento_tipo_id', 'municipio_id', 'rol_participacion']);
        $user = $request->user();

        $eventos = Evento::with(['tipo', 'municipio', 'oficina', 'creador'])
            ->when($q, function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('descripcion', 'like', "%$q%")
                        ->orWhere('lugar', 'like', "%$q%");
                });
            })
            ->when($filters['evento_tipo_id'] ?? null, fn ($query, $value) => $query->where('evento_tipo_id', $value))
            ->when($filters['municipio_id'] ?? null, fn ($query, $value) => $query->where('municipio_id', $value))
            ->when($filters['rol_participacion'] ?? null, fn ($query, $value) => $query->where('rol_participacion', $value))
            ->when($user?->hasRole('delegado'), fn ($query) => $query->where('created_by', $user->uuid))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('eventos.index', [
            'eventos' => $eventos,
            'q' => $q,
            'filters' => $filters,
            'tipos' => EventoTipo::orderBy('nombre')->get(['id', 'nombre', 'activo']),
            'municipios' => $this->availableMunicipios(),
            'rolesParticipacion' => Evento::rolesParticipacion(),
        ]);
    }

    public function create()
    {
        $this->authorize('create', Evento::class);

        return view('eventos.create', [
            'tipos' => EventoTipo::where('activo', true)->orderBy('nombre')->get(['id', 'nombre']),
            'municipios' => $this->availableMunicipios(),
            'rolesParticipacion' => Evento::rolesParticipacion(),
        ]);
    }

    public function store(StoreEventoRequest $request)
    {
        $data = $request->validated();
        $data['created_by'] = $request->user()->uuid;
        $data['oficina_id'] = $this->resolveOficinaId($request);

        Evento::create($data);

        return redirect()->route('eventos.index')->with('status', 'Evento registrado correctamente');
    }

    public function edit(Evento $evento)
    {
        $this->authorize('update', $evento);

        $tipos = EventoTipo::where('activo', true)
            ->orWhere('id', $evento->evento_tipo_id)
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'activo']);

        return view('eventos.edit', [
            'evento' => $evento,
            'tipos' => $tipos,
            'municipios' => $this->availableMunicipios(),
            'rolesParticipacion' => Evento::rolesParticipacion(),
        ]);
    }

    public function update(UpdateEventoRequest $request, Evento $evento)
    {
        $data = $request->validated();
        $data['oficina_id'] = $this->resolveOficinaId($request, $evento);

        $evento->update($data);

        return redirect()->route('eventos.index')->with('status', 'Evento actualizado correctamente');
    }

    public function destroy(Evento $evento)
    {
        $this->authorize('delete', $evento);

        $evento->delete();

        return redirect()->route('eventos.index')->with('status', 'Evento eliminado');
    }

    protected function availableMunicipios()
    {
        $query = Municipio::orderBy('nombre');
        $user = auth()->user();

        if ($user?->hasRole('delegado')) {
            $query->where('oficina_id', $user->oficina_id);
        }

        return $query->get(['id', 'nombre', 'oficina_id']);
    }

    protected function resolveOficinaId(Request $request, ?Evento $evento = null): ?int
    {
        $user = $request->user();
        if ($user?->hasRole('delegado')) {
            return $user->oficina_id;
        }

        $municipioOffice = Municipio::find($request->input('municipio_id'))?->oficina_id;

        return $municipioOffice ?: ($evento?->oficina_id ?: $user?->oficina_id);
    }
}
