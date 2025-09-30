<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    foreach (['admin', 'capturista', 'encargado_360', 'encargado_bienestar', 'psicologo'] as $role) {
        Role::firstOrCreate([
            'name' => $role,
            'guard_name' => 'web',
        ]);
    }

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
});

it('permite a un administrador crear un nuevo usuario', function () {
    $response = $this->actingAs($this->admin)->post('/admin/usuarios', [
        'name' => 'Nuevo Usuario',
        'email' => 'nuevo@example.com',
        'password' => 'Password1',
        'role' => 'capturista',
    ]);

    $response->assertRedirect(route('admin.usuarios.index'));
    $response->assertSessionHas('status', 'Usuario creado correctamente');

    $user = User::where('email', 'nuevo@example.com')->first();

    expect($user)->not->toBeNull();
    expect($user->name)->toBe('Nuevo Usuario');
    expect(Hash::check('Password1', $user->password))->toBeTrue();
    expect($user->hasRole('capturista'))->toBeTrue();
});
