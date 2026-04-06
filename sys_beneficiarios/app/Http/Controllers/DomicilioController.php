<?php

namespace App\Http\Controllers;

use App\Models\Beneficiario;
use App\Models\Domicilio;
use App\Models\Municipio;
use App\Models\User;
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
        $domicilios = $this->domiciliosQuery($request->user())
            ->with(['beneficiario', 'municipio', 'seccion'])
            ->when($q, function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('calle', 'like', "%$q%")
                        ->orWhere('colonia', 'like', "%$q%")
                        ->orWhere('codigo_postal', 'like', "%$q%")
                        ->orWhereHas('municipio', fn ($mq) => $mq->where('nombre', 'like', "%$q%"))
                        ->orWhereHas('seccion', fn ($sq) => $sq->where('seccional', 'like', "%$q%"));
                });
            })
            ->orderBy('created_at', 'desc')
            ->paginate(15)
            ->withQueryString();

        return view('domicilios.index', compact('domicilios', 'q'));
    }

    public function create()
    {
        $beneficiarios = $this->beneficiariosQuery(auth()->user())
            ->orderBy('nombre')
            ->select(['id', 'nombre', 'apellido_paterno', 'apellido_materno', 'folio_tarjeta'])
            ->limit(100)
            ->get();
        $municipios = $this->municipiosQuery(auth()->user())->pluck('nombre', 'id');

        return view('domicilios.create', compact('beneficiarios', 'municipios'));
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);
        $beneficiario = Beneficiario::findOrFail($data['beneficiario_id']);
        $this->ensureCanManageBeneficiario($request->user(), $beneficiario);

        $domicilio = new Domicilio($data);
        $domicilio->id = (string) Str::uuid();
        $domicilio->save();

        return redirect()->route('domicilios.index')->with('status', 'Domicilio creado correctamente');
    }

    public function edit(Domicilio $domicilio)
    {
        $this->ensureCanManageDomicilio(auth()->user(), $domicilio);

        $beneficiarios = $this->beneficiariosQuery(auth()->user())
            ->orderBy('nombre')
            ->select(['id', 'nombre', 'apellido_paterno', 'apellido_materno', 'folio_tarjeta'])
            ->limit(100)
            ->get();
        $municipios = $this->municipiosQuery(auth()->user())->pluck('nombre', 'id');

        return view('domicilios.edit', compact('domicilio', 'beneficiarios', 'municipios'));
    }

    public function update(Request $request, Domicilio $domicilio)
    {
        $this->ensureCanManageDomicilio($request->user(), $domicilio);

        $data = $this->validateData($request, $domicilio);
        $beneficiario = Beneficiario::findOrFail($data['beneficiario_id']);
        $this->ensureCanManageBeneficiario($request->user(), $beneficiario);

        $domicilio->fill($data);
        $domicilio->save();

        return redirect()->route('domicilios.index')->with('status', 'Domicilio actualizado correctamente');
    }

    public function destroy(Domicilio $domicilio)
    {
        $this->ensureCanManageDomicilio(auth()->user(), $domicilio);
        $domicilio->delete();

        return redirect()->route('domicilios.index')->with('status', 'Domicilio eliminado');
    }

    protected function validateData(Request $request, ?Domicilio $domicilio = null): array
    {
        $data = $request->validate([
            'beneficiario_id' => ['required', Rule::exists('beneficiarios', 'id')],
            'calle' => ['required', 'string', 'max:255'],
            'numero_ext' => ['required', 'string', 'max:50'],
            'numero_int' => ['nullable', 'string', 'max:50'],
            'colonia' => ['required', 'string', 'max:255'],
            'municipio_id' => ['nullable', 'exists:municipios,id'],
            'codigo_postal' => ['required', 'string', 'max:20'],
            'seccional' => ['required', 'string', 'max:255', new ValidSeccional()],
        ]);

        $seccion = SeccionResolver::resolve($data['seccional'] ?? null);
        if (! $seccion) {
            throw ValidationException::withMessages([
                'seccional' => 'La seccional no se encuentra en el catalogo.',
            ]);
        }

        if (! empty($data['municipio_id']) && (string) $data['municipio_id'] !== (string) $seccion->municipio_id) {
            throw ValidationException::withMessages([
                'municipio_id' => 'El municipio no coincide con la seccional seleccionada.',
            ]);
        }

        $data['seccion_id'] = $seccion->id;
        $data['municipio_id'] = $seccion->municipio_id;
        unset($data['seccional']);

        return $data;
    }

    protected function domiciliosQuery(?User $user)
    {
        return Domicilio::query()
            ->when($user?->hasRole('capturista'), fn ($query) => $query->whereHas('beneficiario', fn ($beneficiarios) => $beneficiarios->where('created_by', $user->uuid)))
            ->when($user?->hasRole('delegado') && $user->oficina_id, function ($query) use ($user) {
                $query->where(function ($scope) use ($user) {
                    $scope->whereHas('municipio', fn ($municipios) => $municipios->where('oficina_id', $user->oficina_id))
                        ->orWhereHas('beneficiario.tarjeta', fn ($tarjetas) => $tarjetas->where('oficina_id', $user->oficina_id));
                });
            });
    }

    protected function beneficiariosQuery(?User $user)
    {
        return Beneficiario::query()
            ->when($user?->hasRole('capturista'), fn ($query) => $query->where('created_by', $user->uuid))
            ->when($user?->hasRole('delegado') && $user->oficina_id, function ($query) use ($user) {
                $query->where(function ($scope) use ($user) {
                    $scope->whereHas('municipio', fn ($municipios) => $municipios->where('oficina_id', $user->oficina_id))
                        ->orWhereHas('tarjeta', fn ($tarjetas) => $tarjetas->where('oficina_id', $user->oficina_id));
                });
            });
    }

    protected function municipiosQuery(?User $user)
    {
        return Municipio::query()
            ->orderBy('nombre')
            ->when($user?->hasAnyRole(['delegado', 'capturista']) && $user->oficina_id, fn ($query) => $query->where('oficina_id', $user->oficina_id));
    }

    protected function ensureCanManageDomicilio(?User $user, Domicilio $domicilio): void
    {
        if (! $user || $user->hasRole('admin')) {
            return;
        }

        $domicilio->loadMissing(['beneficiario.tarjeta', 'beneficiario.municipio', 'municipio']);

        if ($user->hasRole('capturista') && $domicilio->beneficiario?->created_by !== $user->uuid) {
            abort(403);
        }

        if ($user->hasRole('delegado')) {
            $officeId = $domicilio->beneficiario?->tarjeta?->oficina_id
                ?? $domicilio->beneficiario?->municipio?->oficina_id
                ?? $domicilio->municipio?->oficina_id;

            if ($officeId !== null && (int) $officeId !== (int) $user->oficina_id) {
                abort(403);
            }
        }
    }

    protected function ensureCanManageBeneficiario(?User $user, Beneficiario $beneficiario): void
    {
        if (! $user || $user->hasRole('admin')) {
            return;
        }

        $beneficiario->loadMissing(['tarjeta', 'municipio']);

        if ($user->hasRole('capturista') && $beneficiario->created_by !== $user->uuid) {
            abort(403);
        }

        if ($user->hasRole('delegado')) {
            $officeId = $beneficiario->tarjeta?->oficina_id ?? $beneficiario->municipio?->oficina_id;
            if ($officeId !== null && (int) $officeId !== (int) $user->oficina_id) {
                abort(403);
            }
        }
    }
}
