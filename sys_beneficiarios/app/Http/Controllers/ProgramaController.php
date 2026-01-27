<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProgramaRequest;
use App\Http\Requests\UpdateProgramaRequest;
use App\Models\Programa;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ProgramaController extends Controller
{
    public function index(Request $request)
    {
        $q = $request->get('q');
        $activo = $request->get('activo');

        $programas = Programa::query()
            ->when($q, function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('nombre', 'like', "%$q%")
                        ->orWhere('slug', 'like', "%$q%");
                });
            })
            ->when($activo !== null && $activo !== '', fn($query) => $query->where('activo', (bool) $activo))
            ->orderBy('nombre')
            ->paginate(15)
            ->withQueryString();

        return view('programas.index', compact('programas', 'q', 'activo'));
    }

    public function create()
    {
        return view('programas.create');
    }

    public function store(StoreProgramaRequest $request)
    {
        $data = $request->validated();
        $data['slug'] = $this->ensureSlug($data['slug'] ?? null, $data['nombre']);

        Programa::create($data);

        return redirect()->route('programas.index')->with('status', 'Programa creado correctamente');
    }

    public function edit(Programa $programa)
    {
        return view('programas.edit', compact('programa'));
    }

    public function update(UpdateProgramaRequest $request, Programa $programa)
    {
        $data = $request->validated();
        $data['slug'] = $this->ensureSlug($data['slug'] ?? null, $data['nombre'], $programa);
        $programa->fill($data);
        $programa->save();

        return redirect()->route('programas.index')->with('status', 'Programa actualizado correctamente');
    }

    public function destroy(Programa $programa)
    {
        $programa->delete();
        return redirect()->route('programas.index')->with('status', 'Programa eliminado');
    }

    protected function ensureSlug(?string $slug, string $nombre, ?Programa $ignore = null): string
    {
        $base = Str::slug($slug ?: $nombre);
        if ($base === '') {
            $base = 'programa';
        }
        $candidate = $base;
        $suffix = 1;

        while ($this->slugExists($candidate, $ignore)) {
            $candidate = $base . '-' . $suffix;
            $suffix++;
        }

        return $candidate;
    }

    protected function slugExists(string $slug, ?Programa $ignore = null): bool
    {
        $query = Programa::where('slug', $slug);
        if ($ignore) {
            $query->where('id', '!=', $ignore->id);
        }
        return $query->exists();
    }
}
