<?php

namespace App\Http\Controllers;

use App\Models\Beneficiario;
use App\Models\Domicilio;
use App\Models\Municipio;
use Illuminate\Http\Request;
use App\Http\Requests\StoreBeneficiarioRequest;
use App\Http\Requests\UpdateBeneficiarioRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use App\Models\Seccion;

class BeneficiarioController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', Beneficiario::class);
        $q = $request->get('q');
        $filters = $request->only([
            'municipio_id','seccional','distrito_local','distrito_federal','sexo','discapacidad','edad_min','edad_max'
        ]);

        $baseQuery = Beneficiario::with(['municipio','creador'])
            ->when($q, function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('folio_tarjeta', 'like', "%$q%")
                        ->orWhere('curp', 'like', "%$q%")
                        ->orWhere('nombre', 'like', "%$q%")
                        ->orWhere('apellido_paterno', 'like', "%$q%")
                        ->orWhere('apellido_materno', 'like', "%$q%");
                });
            })
            ->when($filters['municipio_id'] ?? null, fn($q2,$v)=>$q2->where('municipio_id',$v))
            ->when($filters['seccional'] ?? null, fn($q2,$v)=>$q2->where('seccional','like',"%$v%"))
            ->when($filters['distrito_local'] ?? null, fn($q2,$v)=>$q2->where('distrito_local','like',"%$v%"))
            ->when($filters['distrito_federal'] ?? null, fn($q2,$v)=>$q2->where('distrito_federal','like',"%$v%"))
            ->when(($filters['sexo'] ?? '') !== '', fn($q2,$v)=>$q2->where('sexo',$v))
            ->when(($filters['discapacidad'] ?? '') !== '', fn($q2,$v)=>$q2->where('discapacidad',(bool)$v))
            ->when($filters['edad_min'] ?? null, fn($q2,$v)=>$q2->where('edad','>=',(int)$v))
            ->when($filters['edad_max'] ?? null, fn($q2,$v)=>$q2->where('edad','<=',(int)$v))
            ->when(auth()->user()?->hasRole('capturista'), function ($q2) {
                $q2->where('created_by', auth()->user()->uuid);
            });

        if ($request->wantsJson()) {
            $limit = max(1, min($request->integer('limit', 20), 50));
            $items = (clone $baseQuery)
                ->with('municipio:id,nombre')
                ->orderBy('created_at', 'desc')
                ->limit($limit)
                ->get();

            $payload = $items->map(fn ($row) => [
                'id' => $row->id,
                'nombre' => trim(sprintf('%s %s %s', $row->nombre, $row->apellido_paterno, $row->apellido_materno)),
                'curp' => $row->curp,
                'folio_tarjeta' => $row->folio_tarjeta,
                'municipio' => optional($row->municipio)->nombre,
            ]);

            return response()->json(['items' => $payload]);
        }

        $beneficiarios = (clone $baseQuery)
            ->orderBy('created_at','desc')
            ->paginate(15)
            ->withQueryString();

        $municipios = Municipio::orderBy('nombre')->pluck('nombre','id');

        return view('beneficiarios.index', [
            'beneficiarios' => $beneficiarios,
            'q' => $q,
            'filters' => $filters,
            'municipios' => $municipios,
        ]);
    }

    public function create()
    {
        $municipios = Municipio::orderBy('nombre')->pluck('nombre','id');
        return view('beneficiarios.create', compact('municipios'));
    }

    public function store(StoreBeneficiarioRequest $request)
    {
        $data = $request->validated();

        try {
            $beneficiario = DB::transaction(function () use ($request, $data) {
                $beneficiario = new Beneficiario($data);
                // Calcular distritos y municipio desde seccional capturado en domicilio
                $dom = $request->input('domicilio', []);
                if ($dom) {
                    $beneficiario->seccional = $dom['seccional'] ?? $beneficiario->seccional;
                    $comp = $this->computeFromSeccional($dom['seccional'] ?? null);
                    if ($comp) {
                        $beneficiario->distrito_local = $comp['distrito_local'];
                        $beneficiario->distrito_federal = $comp['distrito_federal'];
                        $beneficiario->municipio_id = $dom['municipio_id'] ?? $comp['municipio_id'];
                    } elseif (isset($dom['municipio_id'])) {
                        $beneficiario->municipio_id = $dom['municipio_id'];
                    }
                }
                $beneficiario->id = (string) Str::uuid();
                $beneficiario->created_by = Auth::user()->uuid;

                if (!$beneficiario->save()) {
                    throw new \RuntimeException('No se pudo guardar el beneficiario');
                }

                $this->saveDomicilio($request, $beneficiario);

                return $beneficiario;
            });
        } catch (\Throwable $e) {
            Log::error('Error al registrar beneficiario', [
                'message' => $e->getMessage(),
                'user_id' => Auth::user()?->uuid,
            ]);

            return back()
                ->withInput()
                ->with('error', 'No se pudo registrar el beneficiario, intenta nuevamente.');
        }

        return redirect()->route('beneficiarios.create')
            ->with('status', 'Registrado')
            ->with('last_beneficiario_id', $beneficiario->id)
            ->with('beneficiario_registered', true);
    }

    public function edit(Beneficiario $beneficiario)
    {
        $this->authorize('view', $beneficiario);
        $municipios = Municipio::orderBy('nombre')->pluck('nombre','id');
        $domicilio = $beneficiario->domicilio;
        return view('beneficiarios.edit', compact('beneficiario','municipios','domicilio'));
    }

    public function update(UpdateBeneficiarioRequest $request, Beneficiario $beneficiario)
    {
        $this->authorize('update', $beneficiario);
        $data = $request->validated();
        $beneficiario->fill($data);
        // Calcular distritos/municipio desde seccional del domicilio
        $dom = $request->input('domicilio', []);
        if ($dom) {
            $beneficiario->seccional = $dom['seccional'] ?? $beneficiario->seccional;
            $comp = $this->computeFromSeccional($dom['seccional'] ?? null);
            if ($comp) {
                $beneficiario->distrito_local = $comp['distrito_local'];
                $beneficiario->distrito_federal = $comp['distrito_federal'];
                $beneficiario->municipio_id = $dom['municipio_id'] ?? $comp['municipio_id'];
            } elseif (isset($dom['municipio_id'])) {
                $beneficiario->municipio_id = $dom['municipio_id'];
            }
        }
        $beneficiario->save();

        $this->saveDomicilio($request, $beneficiario);

        return redirect()->route('beneficiarios.index')->with('status', 'Beneficiario actualizado correctamente');
    }

    public function destroy(Beneficiario $beneficiario)
    {
        $this->authorize('delete', $beneficiario);
        $beneficiario->delete();
        return redirect()->route('beneficiarios.index')->with('status', 'Beneficiario eliminado');
    }

    protected function saveDomicilio(Request $request, Beneficiario $beneficiario): void
    {
        $dom = $request->input('domicilio');
        if (!$dom) {
            return;
        }
        // Completar valores de domicilio desde seccional
        $comp = $this->computeFromSeccional($dom['seccional'] ?? null);
        if ($comp) {
            $dom['distrito_local'] = $dom['distrito_local'] ?? $comp['distrito_local'];
            $dom['distrito_federal'] = $dom['distrito_federal'] ?? $comp['distrito_federal'];
            $dom['municipio_id'] = $dom['municipio_id'] ?? $comp['municipio_id'];
        }
        $municipioId = $dom['municipio_id'] ?? $beneficiario->municipio_id ?? null;
        $municipioNombre = null;
        if ($municipioId) {
            $municipioNombre = Municipio::whereKey($municipioId)->value('nombre');
        }
        $payload = array_filter([
            'calle' => $dom['calle'] ?? null,
            'numero_ext' => $dom['numero_ext'] ?? null,
            'numero_int' => $dom['numero_int'] ?? null,
            'colonia' => $dom['colonia'] ?? null,
            'municipio' => $municipioNombre,
            'municipio_id' => $dom['municipio_id'] ?? null,
            'codigo_postal' => $dom['codigo_postal'] ?? null,
            'seccional' => $dom['seccional'] ?? null,
            'distrito_local' => $dom['distrito_local'] ?? null,
            'distrito_federal' => $dom['distrito_federal'] ?? null,
        ], fn ($v) => !is_null($v));
        if (empty($payload)) {
            return;
        }
        $domicilio = $beneficiario->domicilio ?: new Domicilio(['id' => (string) Str::uuid(), 'beneficiario_id' => $beneficiario->id]);
        $domicilio->fill($payload);
        $domicilio->beneficiario_id = $beneficiario->id;
        $domicilio->save();
    }
    private function computeFromSeccional(?string $raw): ?array
    {
        $raw = trim((string)($raw ?? ''));
        if ($raw === '') return null;
        $candidates = array_unique([
            $raw,
            ltrim($raw, '0'),
            str_pad(ltrim($raw, '0'), 4, '0', STR_PAD_LEFT),
        ]);
        $sec = Seccion::whereIn('seccional', $candidates)->first();
        if (!$sec) return null;
        return [
            'municipio_id' => $sec->municipio_id,
            'distrito_local' => $sec->distrito_local,
            'distrito_federal' => $sec->distrito_federal,
        ];
    }
}

