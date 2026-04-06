<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Oficina;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    protected function roleOptions(): array
    {
        return [
            'admin' => 'Admin',
            'delegado' => 'Delegado',
            'capturista' => 'Capturista',
            'capturista_programas' => 'Capturista Programas',
            'skate_plaza' => 'Skate Plaza',
        ];
    }

    public function index()
    {
        $users = User::with(['roles', 'office'])->orderBy('name')->paginate(15);
        return view('admin.users.index', compact('users'));
    }

    public function create()
    {
        $roleOptions = $this->roleOptions();
        $allowed = array_keys($roleOptions);
        $roles = Role::whereIn('name', $allowed)
            ->orderByRaw($this->roleSortSql())
            ->pluck('name')
            ->mapWithKeys(fn ($name) => [$name => $roleOptions[$name] ?? $name])
            ->toArray();
        $offices = Oficina::where('activo', true)->orderBy('nombre')->get(['id', 'nombre', 'tipo']);
        return view('admin.users.create', compact('roles', 'offices'));
    }

    public function store(Request $request)
    {
        $allRoles = Role::whereIn('name', array_keys($this->roleOptions()))->pluck('name')->toArray();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', PasswordRule::min(8)->mixedCase()->numbers()],
            'role' => ['required', Rule::in($allRoles)],
            'oficina_id' => ['nullable', 'exists:oficinas,id'],
        ]);
        $this->validateOfficeAssignment($data);

        $user = new User();
        $user->name = $data['name'];
        $user->email = $data['email'];
        $user->password = $data['password']; // hashed by cast
        $user->oficina_id = $data['oficina_id'] ?? null;
        $user->email_verified_at = now();
        $user->save();

        $user->syncRoles([$data['role']]);

        return redirect()->route('admin.usuarios.index')->with('status', 'Usuario creado correctamente');
    }

    public function edit(User $usuario)
    {
        $roleOptions = $this->roleOptions();
        $allowed = array_keys($roleOptions);
        $roles = Role::whereIn('name', $allowed)
            ->orderByRaw($this->roleSortSql())
            ->pluck('name')
            ->mapWithKeys(fn ($name) => [$name => $roleOptions[$name] ?? $name])
            ->toArray();
        $offices = Oficina::where('activo', true)->orderBy('nombre')->get(['id', 'nombre', 'tipo']);
        $currentRole = $usuario->getRoleNames()->first();
        return view('admin.users.edit', [
            'user' => $usuario,
            'roles' => $roles,
            'offices' => $offices,
            'currentRole' => $currentRole,
        ]);
    }

    public function update(Request $request, User $usuario)
    {
        $allRoles = Role::whereIn('name', array_keys($this->roleOptions()))->pluck('name')->toArray();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users','email')->ignore($usuario->id)],
            'password' => ['nullable', 'string', PasswordRule::min(8)->mixedCase()->numbers()],
            'role' => ['required', Rule::in($allRoles)],
            'oficina_id' => ['nullable', 'exists:oficinas,id'],
        ]);
        $this->validateOfficeAssignment($data);

        $usuario->name = $data['name'];
        $usuario->email = $data['email'];
        $usuario->oficina_id = $data['oficina_id'] ?? null;
        if (!empty($data['password'])) {
            $usuario->password = $data['password']; // hashed by cast
        }
        $usuario->save();

        $usuario->syncRoles([$data['role']]);

        return redirect()->route('admin.usuarios.index')->with('status', 'Usuario actualizado correctamente');
    }

    public function destroy(User $usuario)
    {
        $usuario->delete();
        return redirect()->route('admin.usuarios.index')->with('status', 'Usuario eliminado correctamente');
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

    protected function validateOfficeAssignment(array $data): void
    {
        $role = $data['role'] ?? null;
        $officeId = $data['oficina_id'] ?? null;

        if (in_array($role, ['delegado', 'capturista'], true) && ! $officeId) {
            throw ValidationException::withMessages([
                'oficina_id' => 'Debes asignar una oficina al usuario.',
            ]);
        }

        if ($role === 'delegado' && $officeId) {
            $office = Oficina::find($officeId);
            if (! $office || $office->tipo !== Oficina::TIPO_DELEGACION) {
                throw ValidationException::withMessages([
                    'oficina_id' => 'El delegado debe pertenecer a una oficina de tipo delegacion.',
                ]);
            }
        }
    }
}
