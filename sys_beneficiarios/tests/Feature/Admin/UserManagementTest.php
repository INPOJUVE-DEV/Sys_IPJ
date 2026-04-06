<?php

use App\Models\Oficina;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    foreach (['admin', 'delegado', 'capturista', 'capturista_programas', 'skate_plaza'] as $role) {
        Role::firstOrCreate([
            'name' => $role,
            'guard_name' => 'web',
        ]);
    }

    $this->delegacion = Oficina::create([
        'nombre' => 'Delegacion Norte',
        'tipo' => Oficina::TIPO_DELEGACION,
        'activo' => true,
    ]);
    $this->central = Oficina::create([
        'nombre' => 'Central',
        'tipo' => Oficina::TIPO_CENTRAL,
        'activo' => true,
    ]);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
});

it('permite a un administrador crear un nuevo usuario', function () {
    $response = $this->actingAs($this->admin)->post('/admin/usuarios', [
        'name' => 'Nuevo Usuario',
        'email' => 'nuevo@example.com',
        'password' => 'Password1',
        'role' => 'capturista',
        'oficina_id' => $this->delegacion->id,
    ]);

    $response->assertRedirect(route('admin.usuarios.index'));
    $response->assertSessionHas('status', 'Usuario creado correctamente');

    $user = User::where('email', 'nuevo@example.com')->first();

    expect($user)->not->toBeNull();
    expect($user->name)->toBe('Nuevo Usuario');
    expect(Hash::check('Password1', $user->password))->toBeTrue();
    expect($user->hasRole('capturista'))->toBeTrue();
    expect($user->oficina_id)->toBe($this->delegacion->id);
});

it('rechaza delegados asignados a una oficina central', function () {
    $response = $this->actingAs($this->admin)->post('/admin/usuarios', [
        'name' => 'Delegado Invalido',
        'email' => 'delegado-invalido@example.com',
        'password' => 'Password1',
        'role' => 'delegado',
        'oficina_id' => $this->central->id,
    ]);

    $response->assertSessionHasErrors('oficina_id');
});

it('permite crear un usuario skate plaza sin oficina', function () {
    $response = $this->actingAs($this->admin)->post('/admin/usuarios', [
        'name' => 'Operador Skate',
        'email' => 'skate@example.com',
        'password' => 'Password1',
        'role' => 'skate_plaza',
        'oficina_id' => null,
    ]);

    $response->assertRedirect(route('admin.usuarios.index'));

    $user = User::where('email', 'skate@example.com')->first();

    expect($user)->not->toBeNull();
    expect($user->hasRole('skate_plaza'))->toBeTrue();
    expect($user->oficina_id)->toBeNull();
});
