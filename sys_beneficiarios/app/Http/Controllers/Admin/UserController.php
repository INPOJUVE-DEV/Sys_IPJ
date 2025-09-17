<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index()
    {
        $users = User::with('roles')->orderBy('name')->paginate(15);
        return view('admin.users.index', compact('users'));
    }

    public function create()
    {
        $roles = Role::pluck('name', 'name');
        return view('admin.users.create', compact('roles'));
    }

    public function store(Request $request)
    {
        $allRoles = Role::pluck('name')->toArray();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', PasswordRule::min(8)->mixedCase()->numbers()],
            'role' => ['required', Rule::in($allRoles)],
        ]);

        $user = new User();
        $user->name = $data['name'];
        $user->email = $data['email'];
        $user->password = $data['password']; // hashed by cast
        $user->email_verified_at = now();
        $user->save();

        $user->syncRoles([$data['role']]);

        return redirect()->route('admin.usuarios.index')->with('status', 'Usuario creado correctamente');
    }

    public function edit(User $usuario)
    {
        $roles = Role::pluck('name', 'name');
        $currentRole = $usuario->getRoleNames()->first();
        return view('admin.users.edit', [
            'user' => $usuario,
            'roles' => $roles,
            'currentRole' => $currentRole,
        ]);
    }

    public function update(Request $request, User $usuario)
    {
        $allRoles = Role::pluck('name')->toArray();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users','email')->ignore($usuario->id)],
            'password' => ['nullable', 'string', PasswordRule::min(8)->mixedCase()->numbers()],
            'role' => ['required', Rule::in($allRoles)],
        ]);

        $usuario->name = $data['name'];
        $usuario->email = $data['email'];
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
}
