<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreInscripcionRequest;
use App\Http\Requests\UpdateInscripcionRequest;
use App\Models\Inscripcion;
use App\Models\Municipio;
use App\Models\Programa;
use App\Services\Beneficiarios\BeneficiarioRegistrationService;
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
        $user = $request->user();

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
            ->when($user?->hasRole('delegado'), function ($q2) use ($user) {
                $officeId = $user->oficina_id;
                $q2->where(function ($sub) use ($officeId) {
                    $sub->whereHas('beneficiario.municipio', fn ($mq) => $mq->where('oficina_id', $officeId))
                        ->orWhereHas('beneficiario.tarjeta', fn ($tq) => $tq->where('oficina_id', $officeId));
                });
            })
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
        $municipios = $this->availableMunicipios();
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

    public function store(StoreInscripcionRequest $request, BeneficiarioRegistrationService $registrationService)
    {
        $data = $request->validated();

        try {
            $inscripcion = DB::transaction(function () use ($request, $data, $registrationService) {
                $beneficiarioPayload = [
                    'folio_tarjeta' => $data['folio_tarjeta'] ?? null,
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

                $beneficiario = $registrationService->upsertByCurp(
                    $beneficiarioPayload,
                    $request->input('domicilio', []),
                    $request->user(),
                );

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
        $this->ensureCanManageInscripcion($inscripcion);
        $programas = Programa::orderBy('nombre')->get();
        return view('inscripciones.edit', compact('inscripcion', 'programas'));
    }

    public function update(UpdateInscripcionRequest $request, Inscripcion $inscripcion)
    {
        $this->ensureCanManageInscripcion($inscripcion);
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
        $this->ensureCanManageInscripcion($inscripcion);
        $inscripcion->delete();
        return redirect()->route('inscripciones.index')->with('status', 'Inscripcion eliminada');
    }

    private function availableMunicipios()
    {
        $query = Municipio::orderBy('nombre');
        $user = auth()->user();
        if ($user?->hasAnyRole(['delegado', 'capturista', 'capturista_programas']) && $user->oficina_id) {
            $query->where('oficina_id', $user->oficina_id);
        }

        return $query->pluck('nombre', 'id');
    }

    private function ensureCanManageInscripcion(Inscripcion $inscripcion): void
    {
        $user = auth()->user();
        if (! $user?->hasRole('delegado')) {
            return;
        }

        $inscripcion->loadMissing(['beneficiario.municipio', 'beneficiario.tarjeta']);
        $officeId = $inscripcion->beneficiario?->tarjeta?->oficina_id
            ?? $inscripcion->beneficiario?->municipio?->oficina_id;

        if ($officeId !== null && (int) $officeId !== (int) $user->oficina_id) {
            abort(403);
        }
    }
}
