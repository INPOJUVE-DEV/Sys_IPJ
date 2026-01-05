<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateBeneficiarioRequest;
use App\Models\Beneficiario;
use App\Models\Domicilio;
use App\Models\Municipio;
use Illuminate\Http\Request;
use Spatie\Activitylog\Models\Activity;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use App\Support\SeccionResolver;

class MisRegistrosController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', Beneficiario::class);
        $items = Beneficiario::with(['municipio','seccion'])
            ->where('created_by', $request->user()->uuid)
            ->orderByDesc('created_at')
            ->paginate(15);
        return view('mis_registros.index', compact('items'));
    }

    public function show(Beneficiario $beneficiario)
    {
        $this->authorize('view', $beneficiario);
        $activities = Activity::forSubject($beneficiario)->latest()->limit(10)->get();
        return view('mis_registros.show', compact('beneficiario','activities'));
    }

    public function edit(Beneficiario $beneficiario)
    {
        $this->authorize('update', $beneficiario);
        $municipios = Municipio::orderBy('nombre')->pluck('nombre','id');
        $domicilio = $beneficiario->domicilio;
        return view('mis_registros.edit', compact('beneficiario','municipios','domicilio'));
    }

    public function update(UpdateBeneficiarioRequest $request, Beneficiario $beneficiario)
    {
        $this->authorize('update', $beneficiario);
        $data = $request->validated();
        $dom = $data['domicilio'] ?? [];
        $seccion = SeccionResolver::resolve($dom['seccional'] ?? null);
        if (! $seccion) {
            throw ValidationException::withMessages([
                'domicilio.seccional' => 'La seccional no se encuentra en el catálogo.',
            ]);
        }

        $beneficiario->fill($data);
        $beneficiario->seccion()->associate($seccion);
        $beneficiario->municipio_id = $dom['municipio_id'] ?? $seccion->municipio_id;
        $beneficiario->save();

        $d = $beneficiario->domicilio ?: new Domicilio([
            'id' => (string) Str::uuid(),
            'beneficiario_id' => $beneficiario->id,
        ]);

        $d->fill([
            'calle' => $dom['calle'] ?? '',
            'numero_ext' => $dom['numero_ext'] ?? '',
            'numero_int' => $dom['numero_int'] ?? null,
            'colonia' => $dom['colonia'] ?? '',
            'municipio_id' => $dom['municipio_id'] ?? $seccion->municipio_id,
            'codigo_postal' => $dom['codigo_postal'] ?? '',
            'seccion_id' => $seccion->id,
        ])->save();

        return redirect()->route('mis-registros.show', $beneficiario)->with('status', 'Actualizado correctamente');
    }
}
