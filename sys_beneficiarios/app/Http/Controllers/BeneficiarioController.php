<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBeneficiarioRequest;
use App\Http\Requests\UpdateBeneficiarioRequest;
use App\Models\Beneficiario;
use App\Models\Domicilio;
use App\Models\Municipio;
use App\Models\Seccion;
use App\Support\SeccionResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BeneficiarioController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', Beneficiario::class);
        $q = trim((string) $request->get('q'));
        $q = $q !== '' ? $q : null;

        $baseQuery = Beneficiario::with(['municipio', 'creador', 'seccion'])
            ->when($q, function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('folio_tarjeta', 'like', "%$q%")
                        ->orWhere('curp', 'like', "%$q%");
                });
            })
            ->when(auth()->user()?->hasRole('capturista'), function ($q2) {
                $q2->where('created_by', auth()->user()->uuid);
            })
            ->when(auth()->user()?->hasRole('delegado'), function ($q2) {
                $officeId = auth()->user()->oficina_id;
                $q2->where(function ($sub) use ($officeId) {
                    $sub->whereHas('municipio', fn ($mq) => $mq->where('oficina_id', $officeId))
                        ->orWhereHas('tarjeta', fn ($tq) => $tq->where('oficina_id', $officeId));
                });
            });

        if ($request->wantsJson()) {
            $limit = max(1, min($request->integer('limit', 20), 50));
            $items = (clone $baseQuery)
                ->with(['municipio:id,nombre', 'seccion:id,seccional'])
                ->orderBy('created_at', 'desc')
                ->limit($limit)
                ->get();

            $payload = $items->map(fn ($row) => [
                'id' => $row->id,
                'nombre' => trim(sprintf('%s %s %s', $row->nombre, $row->apellido_paterno, $row->apellido_materno)),
                'curp' => $row->curp,
                'folio_tarjeta' => $row->folio_tarjeta,
                'municipio' => optional($row->municipio)->nombre,
                'seccional' => optional($row->seccion)->seccional,
            ]);

            return response()->json(['items' => $payload]);
        }

        $beneficiarios = (clone $baseQuery)
            ->orderBy('created_at', 'desc')
            ->paginate(15)
            ->withQueryString();

        return view('beneficiarios.index', [
            'beneficiarios' => $beneficiarios,
            'q' => $q,
        ]);
    }

    public function create()
    {
        $municipios = $this->availableMunicipios();

        return view('beneficiarios.create', compact('municipios'));
    }

    public function store(StoreBeneficiarioRequest $request)
    {
        $data = $request->validated();
        $data['folio_tarjeta'] = trim((string) ($data['folio_tarjeta'] ?? '')) ?: null;

        try {
            $beneficiario = DB::transaction(function () use ($request, $data) {
                $beneficiario = new Beneficiario($data);
                $dom = $request->input('domicilio', []);
                $seccion = $this->resolveSeccionFromInput($dom, $data['id_ine'] ?? null);
                $seccion?->loadMissing('municipio');
                $this->ensureUserCanCaptureSeccion($seccion);

                $inputMunicipioId = $dom['municipio_id'] ?? null;
                if ($inputMunicipioId && $seccion && (string) $inputMunicipioId !== (string) $seccion->municipio_id) {
                    throw ValidationException::withMessages([
                        'domicilio.municipio_id' => 'El municipio no coincide con la seccional detectada.',
                    ]);
                }

                $beneficiario->seccion()->associate($seccion);
                $beneficiario->municipio_id = $seccion?->municipio_id;
                $beneficiario->tarjeta_id = null;
                $beneficiario->id = (string) Str::uuid();
                $beneficiario->created_by = Auth::user()->uuid;

                if (! $beneficiario->save()) {
                    throw new \RuntimeException('No se pudo guardar el beneficiario');
                }

                $this->saveDomicilio($request, $beneficiario, $seccion);

                return $beneficiario;
            });
        } catch (ValidationException $e) {
            throw $e;
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
        $municipios = $this->availableMunicipios();
        $domicilio = $beneficiario->domicilio;

        return view('beneficiarios.edit', compact('beneficiario', 'municipios', 'domicilio'));
    }

    public function update(UpdateBeneficiarioRequest $request, Beneficiario $beneficiario)
    {
        $this->authorize('update', $beneficiario);
        $data = $request->validated();
        $data['folio_tarjeta'] = trim((string) ($data['folio_tarjeta'] ?? '')) ?: null;

        $beneficiario->fill($data);
        $dom = $request->input('domicilio', []);
        $seccion = $this->resolveSeccionFromInput($dom, $data['id_ine'] ?? null, $beneficiario->seccion);
        $seccion?->loadMissing('municipio');
        $this->ensureUserCanCaptureSeccion($seccion);

        $inputMunicipioId = $dom['municipio_id'] ?? null;
        if ($inputMunicipioId && $seccion && (string) $inputMunicipioId !== (string) $seccion->municipio_id) {
            throw ValidationException::withMessages([
                'domicilio.municipio_id' => 'El municipio no coincide con la seccional detectada.',
            ]);
        }

        $beneficiario->seccion()->associate($seccion);
        $beneficiario->municipio_id = $seccion?->municipio_id ?? $beneficiario->municipio_id;
        $beneficiario->save();

        $this->saveDomicilio($request, $beneficiario, $seccion);

        return redirect()->route('beneficiarios.index')->with('status', 'Beneficiario actualizado correctamente');
    }

    public function destroy(Beneficiario $beneficiario)
    {
        $this->authorize('delete', $beneficiario);
        $beneficiario->delete();

        return redirect()->route('beneficiarios.index')->with('status', 'Beneficiario eliminado');
    }

    protected function saveDomicilio(Request $request, Beneficiario $beneficiario, ?Seccion $seccion = null): void
    {
        $dom = $request->input('domicilio');
        if (! $dom) {
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
        ], fn ($v) => ! is_null($v));

        if (empty($payload)) {
            return;
        }

        $domicilio = $beneficiario->domicilio ?: new Domicilio([
            'id' => (string) Str::uuid(),
            'beneficiario_id' => $beneficiario->id,
        ]);

        $domicilio->fill($payload);
        $domicilio->beneficiario_id = $beneficiario->id;
        $domicilio->save();
    }

    private function resolveSeccionFromInput(array $domicilio, ?string $idIne = null, ?Seccion $fallback = null): ?Seccion
    {
        $seccion = SeccionResolver::resolveFromIne($idIne)
            ?: SeccionResolver::resolve($domicilio['seccional'] ?? null)
            ?: $fallback;

        if (! $seccion) {
            throw ValidationException::withMessages([
                'id_ine' => 'No fue posible detectar una seccional valida a partir del ID INE.',
            ]);
        }

        return $seccion;
    }

    private function availableMunicipios()
    {
        $query = Municipio::orderBy('nombre');
        $user = auth()->user();
        if ($user?->hasAnyRole(['delegado', 'capturista']) && $user->oficina_id) {
            $query->where('oficina_id', $user->oficina_id);
        }

        return $query->pluck('nombre', 'id');
    }

    private function ensureUserCanCaptureSeccion(?Seccion $seccion): void
    {
        $user = auth()->user();
        if (! $user?->hasAnyRole(['delegado', 'capturista']) || ! $user->oficina_id || ! $seccion) {
            return;
        }

        $seccion->loadMissing('municipio');
        $officeId = $seccion->municipio?->oficina_id;
        if ($officeId && (int) $officeId !== (int) $user->oficina_id) {
            throw ValidationException::withMessages([
                'id_ine' => 'La seccional detectada no pertenece a tu oficina.',
            ]);
        }
    }
}
