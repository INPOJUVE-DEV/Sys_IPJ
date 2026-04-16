<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Municipio;
use App\Models\Oficina;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    protected function roleOptions(?User $actor = null): array
    {
        if ($actor?->hasRole('delegado')) {
            return [
                'capturista' => 'Capturista',
                'capturista_programas' => 'Capturista Programas',
            ];
        }

        return [
            'admin' => 'Admin',
            'delegado' => 'Delegado',
            'capturista' => 'Capturista',
            'capturista_programas' => 'Capturista Programas',
            'skate_plaza' => 'Skate Plaza',
        ];
    }

    public function index(Request $request)
    {
        $actor = $request->user();
        $users = User::with(['roles', 'office'])
            ->when($actor->hasRole('delegado'), function ($query) use ($actor) {
                $query->where('oficina_id', $actor->oficina_id)
                    ->whereHas('roles', fn ($roles) => $roles->whereIn('name', ['capturista', 'capturista_programas']));
            })
            ->orderBy('name')
            ->paginate(15);

        return view('admin.users.index', compact('users'));
    }

    public function create(Request $request)
    {
        $actor = $request->user();
        $roleOptions = $this->roleOptions($actor);
        $allowed = array_keys($roleOptions);
        $roles = Role::whereIn('name', $allowed)
            ->orderByRaw($this->roleSortSql())
            ->pluck('name')
            ->mapWithKeys(fn ($name) => [$name => $roleOptions[$name] ?? $name])
            ->toArray();
        $offices = Oficina::where('activo', true)
            ->when($actor->hasRole('delegado'), fn ($query) => $query->where('id', $actor->oficina_id))
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'tipo', 'region']);
        $municipiosByRegion = $this->municipiosByRegion();

        return view('admin.users.create', compact('roles', 'offices', 'municipiosByRegion'));
    }

    public function store(Request $request)
    {
        $actor = $request->user();
        $allRoles = Role::whereIn('name', array_keys($this->roleOptions($actor)))->pluck('name')->toArray();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', PasswordRule::min(8)->mixedCase()->numbers()],
            'role' => ['required', Rule::in($allRoles)],
            'oficina_id' => ['nullable', 'exists:oficinas,id'],
            'municipio_ids_present' => ['nullable', 'boolean'],
            'municipio_ids' => ['nullable', 'array'],
            'municipio_ids.*' => ['integer', 'exists:municipios,id'],
        ]);
        if ($actor->hasRole('delegado')) {
            $data['oficina_id'] = $actor->oficina_id;
        }

        $this->validateOfficeAssignment($data, $actor);

        $user = new User();
        $user->name = $data['name'];
        $user->email = $data['email'];
        $user->password = $data['password']; // hashed by cast
        $user->oficina_id = $data['oficina_id'] ?? null;
        $user->email_verified_at = now();
        $user->save();

        $user->syncRoles([$data['role']]);
        $this->syncDelegadoMunicipios($data, $actor);

        return redirect()->route($this->usersRouteName($request, 'index'))->with('status', 'Usuario creado correctamente');
    }

    public function edit(Request $request, User $usuario)
    {
        $actor = $request->user();
        $this->ensureCanManageUser($actor, $usuario);

        $roleOptions = $this->roleOptions($actor);
        $allowed = array_keys($roleOptions);
        $roles = Role::whereIn('name', $allowed)
            ->orderByRaw($this->roleSortSql())
            ->pluck('name')
            ->mapWithKeys(fn ($name) => [$name => $roleOptions[$name] ?? $name])
            ->toArray();
        $offices = Oficina::where('activo', true)
            ->when($actor->hasRole('delegado'), fn ($query) => $query->where('id', $actor->oficina_id))
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'tipo', 'region']);
        $currentRole = $usuario->getRoleNames()->first();
        $municipiosByRegion = $this->municipiosByRegion();

        return view('admin.users.edit', [
            'user' => $usuario,
            'roles' => $roles,
            'offices' => $offices,
            'currentRole' => $currentRole,
            'municipiosByRegion' => $municipiosByRegion,
        ]);
    }

    public function update(Request $request, User $usuario)
    {
        $actor = $request->user();
        $this->ensureCanManageUser($actor, $usuario);

        $allRoles = Role::whereIn('name', array_keys($this->roleOptions($actor)))->pluck('name')->toArray();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users','email')->ignore($usuario->id)],
            'password' => ['nullable', 'string', PasswordRule::min(8)->mixedCase()->numbers()],
            'role' => ['required', Rule::in($allRoles)],
            'oficina_id' => ['nullable', 'exists:oficinas,id'],
            'municipio_ids_present' => ['nullable', 'boolean'],
            'municipio_ids' => ['nullable', 'array'],
            'municipio_ids.*' => ['integer', 'exists:municipios,id'],
        ]);
        if ($actor->hasRole('delegado')) {
            $data['oficina_id'] = $actor->oficina_id;
        }

        $this->validateOfficeAssignment($data, $actor);

        $usuario->name = $data['name'];
        $usuario->email = $data['email'];
        $usuario->oficina_id = $data['oficina_id'] ?? null;
        if (!empty($data['password'])) {
            $usuario->password = $data['password']; // hashed by cast
        }
        $usuario->save();

        $usuario->syncRoles([$data['role']]);
        $this->syncDelegadoMunicipios($data, $actor);

        return redirect()->route($this->usersRouteName($request, 'index'))->with('status', 'Usuario actualizado correctamente');
    }

    public function destroy(Request $request, User $usuario)
    {
        $this->ensureCanManageUser($request->user(), $usuario);
        $usuario->delete();
        return redirect()->route($this->usersRouteName($request, 'index'))->with('status', 'Usuario eliminado correctamente');
    }

    protected function roleSortSql(): string
    {
        return "CASE name
            WHEN 'admin' THEN 1
            WHEN 'delegado' THEN 2
            WHEN 'capturista' THEN 3
            WHEN 'capturista_programas' THEN 4
            WHEN 'skate_plaza' THEN 5
            ELSE 6
        END";
    }

    protected function validateOfficeAssignment(array $data, ?User $actor = null): void
    {
        $role = $data['role'] ?? null;
        $officeId = $data['oficina_id'] ?? null;

        if ($actor?->hasRole('delegado') && ! $actor->oficina_id) {
            throw ValidationException::withMessages([
                'oficina_id' => 'Tu usuario no tiene region asignada.',
            ]);
        }

        if ($actor?->hasRole('delegado') && $officeId && (int) $officeId !== (int) $actor->oficina_id) {
            throw ValidationException::withMessages([
                'oficina_id' => 'Solo puedes crear usuarios dentro de tu region.',
            ]);
        }

        if (in_array($role, ['delegado', 'capturista', 'capturista_programas'], true) && ! $officeId) {
            throw ValidationException::withMessages([
                'oficina_id' => 'Debes asignar una region al usuario.',
            ]);
        }

        if ($role === 'delegado' && $officeId) {
            $office = Oficina::find($officeId);
            if (! $office || $office->tipo !== Oficina::TIPO_DELEGACION) {
                throw ValidationException::withMessages([
                    'oficina_id' => 'El delegado debe pertenecer a una region.',
                ]);
            }
        }
    }

    protected function ensureCanManageUser(User $actor, User $target): void
    {
        if ($actor->hasRole('admin')) {
            return;
        }

        $target->loadMissing('roles');
        if (
            ! $actor->hasRole('delegado')
            || (int) $target->oficina_id !== (int) $actor->oficina_id
            || ! $target->hasAnyRole(['capturista', 'capturista_programas'])
        ) {
            abort(403);
        }
    }

    protected function syncDelegadoMunicipios(array $data, User $actor): void
    {
        if ($actor->hasRole('delegado') || ($data['role'] ?? null) !== 'delegado' || empty($data['oficina_id'])) {
            return;
        }

        $office = Oficina::find($data['oficina_id']);
        if (! $office || ! $office->region) {
            return;
        }

        $selectedSource = array_key_exists('municipio_ids_present', $data)
            ? ($data['municipio_ids'] ?? [])
            : Municipio::where('region', $office->region)->pluck('id')->all();

        $selected = collect($selectedSource)
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->values()
            ->all();

        Municipio::where('region', $office->region)
            ->whereIn('id', $selected)
            ->update(['oficina_id' => $office->id]);

        Municipio::where('region', $office->region)
            ->where('oficina_id', $office->id)
            ->when($selected !== [], fn ($query) => $query->whereNotIn('id', $selected))
            ->when($selected === [], fn ($query) => $query->whereRaw('1 = 1'))
            ->update(['oficina_id' => null]);
    }

    protected function municipiosByRegion()
    {
        return Municipio::query()
            ->whereNotNull('region')
            ->orderBy('region')
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'region', 'oficina_id'])
            ->groupBy('region');
    }

    protected function usersRouteName(Request $request, string $action): string
    {
        return $request->routeIs('delegacion.*')
            ? 'delegacion.usuarios.'.$action
            : 'admin.usuarios.'.$action;
    }
}
