<?php

namespace App\Http\Controllers;

use App\Models\Beneficiario;
use App\Models\Domicilio;
use App\Models\Municipio;
use App\Rules\ValidSeccional;
use App\Support\SeccionResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class DomicilioController extends Controller
{
    public function index(Request $request)
    {
        $q = $request->get('q');
        $domicilios = Domicilio::with(['beneficiario','municipio','seccion'])
            ->when($q, function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('calle', 'like', "%$q%")
                        ->orWhere('colonia', 'like', "%$q%")
                        ->orWhere('codigo_postal', 'like', "%$q%")
                        ->orWhereHas('municipio', fn($mq) => $mq->where('nombre', 'like', "%$q%"))
                        ->orWhereHas('seccion', fn($sq) => $sq->where('seccional', 'like', "%$q%"));
                });
            })
            ->orderBy('created_at','desc')
            ->paginate(15)
            ->withQueryString();

        return view('domicilios.index', compact('domicilios','q'));
    }

    public function create()
    {
        $beneficiarios = Beneficiario::orderBy('nombre')
            ->select(['id','nombre','apellido_paterno','apellido_materno','folio_tarjeta'])
            ->limit(100)
            ->get();
        $municipios = Municipio::orderBy('nombre')->pluck('nombre','id');
        return view('domicilios.create', compact('beneficiarios','municipios'));
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);

        $domicilio = new Domicilio($data);
        $domicilio->id = (string) Str::uuid();
        $domicilio->save();

        return redirect()->route('domicilios.index')->with('status', 'Domicilio creado correctamente');
    }

    public function edit(Domicilio $domicilio)
    {
        $beneficiarios = Beneficiario::orderBy('nombre')
            ->select(['id','nombre','apellido_paterno','apellido_materno','folio_tarjeta'])
            ->limit(100)
            ->get();
        $municipios = Municipio::orderBy('nombre')->pluck('nombre','id');
        return view('domicilios.edit', compact('domicilio','beneficiarios','municipios'));
    }

    public function update(Request $request, Domicilio $domicilio)
    {
        $data = $this->validateData($request, $domicilio);
        $domicilio->fill($data);
        $domicilio->save();
        return redirect()->route('domicilios.index')->with('status', 'Domicilio actualizado correctamente');
    }

    public function destroy(Domicilio $domicilio)
    {
        $domicilio->delete();
        return redirect()->route('domicilios.index')->with('status', 'Domicilio eliminado');
    }

    protected function validateData(Request $request, ?Domicilio $domicilio = null): array
    {
        $data = $request->validate([
            'beneficiario_id' => ['required', Rule::exists('beneficiarios','id')],
            'calle' => ['required','string','max:255'],
            'numero_ext' => ['required','string','max:50'],
            'numero_int' => ['nullable','string','max:50'],
            'colonia' => ['required','string','max:255'],
            'municipio_id' => ['nullable','exists:municipios,id'],
            'codigo_postal' => ['required','string','max:20'],
            'seccional' => ['required','string','max:255', new ValidSeccional()],
        ]);

        $seccion = SeccionResolver::resolve($data['seccional'] ?? null);
        if (! $seccion) {
            throw ValidationException::withMessages([
                'seccional' => 'La seccional no se encuentra en el catálogo.',
            ]);
        }

        $data['seccion_id'] = $seccion->id;
        $data['municipio_id'] = $data['municipio_id'] ?? $seccion->municipio_id;
        unset($data['seccional']);

        return $data;
    }
}
