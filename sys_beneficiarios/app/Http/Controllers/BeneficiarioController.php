<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBeneficiarioRequest;
use App\Http\Requests\UpdateBeneficiarioRequest;
use App\Models\Beneficiario;
use App\Models\Municipio;
use App\Services\Beneficiarios\BeneficiarioRegistrationService;
use App\Services\Integrations\ApiTj\CardholderSyncService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class BeneficiarioController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', Beneficiario::class);
        $q = $request->get('q');
        $filters = $request->only([
            'municipio_id', 'seccional', 'distrito_local', 'distrito_federal', 'sexo', 'discapacidad', 'edad_min', 'edad_max',
        ]);

        $baseQuery = Beneficiario::with(['municipio', 'creador', 'seccion'])
            ->when($q, function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('folio_tarjeta', 'like', "%$q%")
                        ->orWhere('curp', 'like', "%$q%")
                        ->orWhere('nombre', 'like', "%$q%")
                        ->orWhere('apellido_paterno', 'like', "%$q%")
                        ->orWhere('apellido_materno', 'like', "%$q%");
                });
            })
            ->when($filters['municipio_id'] ?? null, fn ($q2, $v) => $q2->where('municipio_id', $v))
            ->when($filters['seccional'] ?? null, fn ($q2, $v) => $q2->whereHas('seccion', fn ($sq) => $sq->where('seccional', 'like', "%$v%")))
            ->when($filters['distrito_local'] ?? null, fn ($q2, $v) => $q2->whereHas('seccion', fn ($sq) => $sq->where('distrito_local', 'like', "%$v%")))
            ->when($filters['distrito_federal'] ?? null, fn ($q2, $v) => $q2->whereHas('seccion', fn ($sq) => $sq->where('distrito_federal', 'like', "%$v%")))
            ->when(($filters['sexo'] ?? '') !== '', fn ($q2, $v) => $q2->where('sexo', $v))
            ->when(($filters['discapacidad'] ?? '') !== '', fn ($q2, $v) => $q2->where('discapacidad', (bool) $v))
            ->when($filters['edad_min'] ?? null, fn ($q2, $v) => $q2->where('edad', '>=', (int) $v))
            ->when($filters['edad_max'] ?? null, fn ($q2, $v) => $q2->where('edad', '<=', (int) $v))
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

        $municipios = $this->availableMunicipios();

        return view('beneficiarios.index', [
            'beneficiarios' => $beneficiarios,
            'q' => $q,
            'filters' => $filters,
            'municipios' => $municipios,
        ]);
    }

    public function create()
    {
        $municipios = $this->availableMunicipios();

        return view('beneficiarios.create', compact('municipios'));
    }

    public function store(
        StoreBeneficiarioRequest $request,
        BeneficiarioRegistrationService $registrationService,
        CardholderSyncService $syncService,
    )
    {
        $data = $request->validated();

        try {
            $beneficiario = $registrationService->create(
                $data,
                $request->input('domicilio', []),
                $request->user(),
            );
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('Error al registrar beneficiario', [
                'message' => $e->getMessage(),
                'user_id' => $request->user()?->uuid,
            ]);

            return back()
                ->withInput()
                ->with('error', 'No se pudo registrar el beneficiario, intenta nuevamente.');
        }

        $statusMessage = 'Registrado';

        try {
            $syncService->queueBeneficiario($beneficiario, $request->user());
        } catch (\Throwable $e) {
            Log::error('Error al programar sincronizacion de beneficiario hacia API_TJ', [
                'message' => $e->getMessage(),
                'beneficiario_id' => $beneficiario->id,
                'user_id' => $request->user()?->uuid,
            ]);

            $statusMessage = 'Registrado. La sincronizacion con API_TJ quedo pendiente de revisar.';
        }

        return redirect()->route('beneficiarios.create')
            ->with('status', $statusMessage)
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

    public function update(UpdateBeneficiarioRequest $request, Beneficiario $beneficiario, BeneficiarioRegistrationService $registrationService)
    {
        $this->authorize('update', $beneficiario);
        $data = $request->validated();
        $registrationService->update(
            $beneficiario,
            $data,
            $request->input('domicilio', []),
            $request->user(),
        );

        return redirect()->route('beneficiarios.index')->with('status', 'Beneficiario actualizado correctamente');
    }

    public function destroy(Beneficiario $beneficiario)
    {
        $this->authorize('delete', $beneficiario);
        $beneficiario->delete();

        return redirect()->route('beneficiarios.index')->with('status', 'Beneficiario eliminado');
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
}
