<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreInscripcionRequest;
use App\Http\Requests\UpdateInscripcionRequest;
use App\Models\Beneficiario;
use App\Models\Domicilio;
use App\Models\Inscripcion;
use App\Models\Municipio;
use App\Models\Programa;
use App\Models\Seccion;
use App\Support\SeccionResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class InscripcionController extends Controller
{
    public function index(Request $request)
    {
        $q = $request->get('q');
        $filters = $request->only(['programa_id', 'periodo', 'estatus']);

        $query = Inscripcion::with(['beneficiario', 'programa', 'creador'])
            ->when($q, function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->whereHas('beneficiario', function ($bq) use ($q) {
                        $bq->where('curp', 'like', "%$q%")
                            ->orWhere('nombre', 'like', "%$q%")
                            ->orWhere('apellido_paterno', 'like', "%$q%")
                            ->orWhere('apellido_materno', 'like', "%$q%");
                    })->orWhereHas('programa', fn ($pq) => $pq->where('nombre', 'like', "%$q%"));
                });
            })
            ->when($filters['programa_id'] ?? null, fn ($q2, $v) => $q2->where('programa_id', $v))
            ->when($filters['periodo'] ?? null, fn ($q2, $v) => $q2->where('periodo', $v))
            ->when($filters['estatus'] ?? null, fn ($q2, $v) => $q2->where('estatus', $v))
            ->orderBy('created_at', 'desc');

        $inscripciones = $query->paginate(15)->withQueryString();
        $programas = Programa::orderBy('nombre')->pluck('nombre', 'id');

        return view('inscripciones.index', [
            'inscripciones' => $inscripciones,
            'q' => $q,
            'filters' => $filters,
            'programas' => $programas,
        ]);
    }

    public function create()
    {
        $programas = Programa::where('activo', true)->orderBy('nombre')->get();
        $municipios = Municipio::orderBy('nombre')->pluck('nombre', 'id');
        $periodo = now()->format('Y-m');
        $dailyCount = null;
        $dailyCountStart = null;

        $user = auth()->user();
        if ($user?->hasRole('capturista_programas')) {
            $now = now();
            $start = (clone $now)->startOfDay()->addMinute();
            if ($now->lt($start)) {
                $start = (clone $now)->subDay()->startOfDay()->addMinute();
            }
            $dailyCount = Inscripcion::where('created_by', $user->uuid)
                ->whereBetween('created_at', [$start, $now])
                ->count();
            $dailyCountStart = $start;
        }

        return view('inscripciones.create', compact('programas', 'municipios', 'periodo', 'dailyCount', 'dailyCountStart'));
    }

    public function store(StoreInscripcionRequest $request)
    {
        $data = $request->validated();

        try {
            $inscripcion = DB::transaction(function () use ($request, $data) {
                $dom = $request->input('domicilio', []);
                $seccion = $this->resolveSeccionFromInput($dom);

                $beneficiario = Beneficiario::where('curp', $data['curp'])->first();
                $payload = [
                    'nombre' => $data['nombre'],
                    'apellido_paterno' => $data['apellido_paterno'],
                    'apellido_materno' => $data['apellido_materno'],
                    'curp' => $data['curp'],
                    'fecha_nacimiento' => $data['fecha_nacimiento'],
                    'sexo' => $data['sexo'],
                    'discapacidad' => $data['discapacidad'],
                    'id_ine' => $data['id_ine'],
                    'telefono' => $data['telefono'],
                ];

                if ($beneficiario) {
                    $beneficiario->fill($payload);
                } else {
                    $beneficiario = new Beneficiario($payload);
                    $beneficiario->id = (string) Str::uuid();
                    $beneficiario->created_by = Auth::user()->uuid;
                }

                if (!empty($data['folio_tarjeta'])) {
                    $conflict = Beneficiario::where('folio_tarjeta', $data['folio_tarjeta'])
                        ->when($beneficiario->id ?? null, fn ($q2, $id) => $q2->where('id', '!=', $id))
                        ->exists();
                    if ($conflict) {
                        throw ValidationException::withMessages([
                            'folio_tarjeta' => 'Este folio ya esta registrado.',
                        ]);
                    }
                    $beneficiario->folio_tarjeta = $data['folio_tarjeta'];
                }

                $beneficiario->seccion()->associate($seccion);
                $beneficiario->municipio_id = $seccion?->municipio_id;
                $beneficiario->save();

                $this->saveDomicilio($request, $beneficiario, $seccion);

                $programa = Programa::findOrFail($data['programa_id']);
                $periodo = $data['periodo'];

                $exists = Inscripcion::where('beneficiario_id', $beneficiario->id)
                    ->where('programa_id', $programa->id)
                    ->where('periodo', $periodo)
                    ->exists();
                if ($exists) {
                    throw ValidationException::withMessages([
                        'periodo' => 'Ya existe una inscripcion de este beneficiario para el periodo seleccionado.',
                    ]);
                }

                $inscripcion = new Inscripcion([
                    'id' => (string) Str::uuid(),
                    'beneficiario_id' => $beneficiario->id,
                    'programa_id' => $programa->id,
                    'periodo' => $periodo,
                    'estatus' => $data['estatus'] ?? 'inscrito',
                    'fecha_renovacion' => $request->boolean('renovacion') && $programa->renovable ? now() : null,
                    'created_by' => Auth::user()->uuid,
                ]);
                $inscripcion->save();

                return $inscripcion;
            });
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('Error al registrar inscripcion', [
                'message' => $e->getMessage(),
                'user_id' => Auth::user()?->uuid,
            ]);

            return back()
                ->withInput()
                ->with('error', 'No se pudo registrar la inscripcion, intenta nuevamente.');
        }

        return redirect()->route('inscripciones.index')->with('status', 'Inscripcion registrada correctamente');
    }

    public function edit(Inscripcion $inscripcion)
    {
        $programas = Programa::orderBy('nombre')->get();
        return view('inscripciones.edit', compact('inscripcion', 'programas'));
    }

    public function update(UpdateInscripcionRequest $request, Inscripcion $inscripcion)
    {
        $data = $request->validated();

        $exists = Inscripcion::where('beneficiario_id', $inscripcion->beneficiario_id)
            ->where('programa_id', $data['programa_id'])
            ->where('periodo', $data['periodo'])
            ->where('id', '!=', $inscripcion->id)
            ->exists();
        if ($exists) {
            throw ValidationException::withMessages([
                'periodo' => 'Ya existe una inscripcion para ese periodo y programa.',
            ]);
        }

        $inscripcion->fill($data);
        $inscripcion->save();

        return redirect()->route('inscripciones.index')->with('status', 'Inscripcion actualizada correctamente');
    }

    public function destroy(Inscripcion $inscripcion)
    {
        $inscripcion->delete();
        return redirect()->route('inscripciones.index')->with('status', 'Inscripcion eliminada');
    }

    protected function saveDomicilio(Request $request, Beneficiario $beneficiario, ?Seccion $seccion = null): void
    {
        $dom = $request->input('domicilio');
        if (!$dom) {
            return;
        }
        $municipioId = $seccion?->municipio_id ?? $beneficiario->municipio_id;
        $payload = array_filter([
            'calle' => $dom['calle'] ?? null,
            'numero_ext' => $dom['numero_ext'] ?? null,
            'numero_int' => $dom['numero_int'] ?? null,
            'colonia' => $dom['colonia'] ?? null,
            'municipio_id' => $municipioId,
            'codigo_postal' => $dom['codigo_postal'] ?? null,
            'seccion_id' => $seccion?->id,
        ], fn ($v) => !is_null($v));
        if (empty($payload)) {
            return;
        }
        $domicilio = $beneficiario->domicilio ?: new Domicilio(['id' => (string) Str::uuid(), 'beneficiario_id' => $beneficiario->id]);
        $domicilio->fill($payload);
        $domicilio->beneficiario_id = $beneficiario->id;
        $domicilio->save();
    }

    private function resolveSeccionFromInput(array $domicilio): ?Seccion
    {
        $seccion = SeccionResolver::resolve($domicilio['seccional'] ?? null);
        if (! $seccion) {
            throw ValidationException::withMessages([
                'domicilio.seccional' => 'La seccional no se encuentra en el catalogo.',
            ]);
        }

        $inputMunicipioId = $domicilio['municipio_id'] ?? null;
        if ($inputMunicipioId && (string) $inputMunicipioId !== (string) $seccion->municipio_id) {
            throw ValidationException::withMessages([
                'domicilio.municipio_id' => 'El municipio no coincide con la seccional seleccionada.',
            ]);
        }

        return $seccion;
    }
}
