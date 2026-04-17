<?php

namespace Tests\Feature;

use App\Models\Evento;
use App\Models\EventoTipo;
use App\Models\Municipio;
use App\Models\Oficina;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventosTest extends TestCase
{
    use RefreshDatabase;

    private Oficina $oficina;
    private Oficina $otraOficina;
    private Municipio $municipio;
    private Municipio $otroMunicipio;
    private EventoTipo $tipo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RoleSeeder::class);

        $this->oficina = Oficina::create([
            'nombre' => 'Delegacion Norte',
            'tipo' => Oficina::TIPO_DELEGACION,
            'activo' => true,
        ]);
        $this->otraOficina = Oficina::create([
            'nombre' => 'Delegacion Sur',
            'tipo' => Oficina::TIPO_DELEGACION,
            'activo' => true,
        ]);
        $this->municipio = Municipio::create([
            'clave' => 1,
            'nombre' => 'Municipio Norte',
            'oficina_id' => $this->oficina->id,
        ]);
        $this->otroMunicipio = Municipio::create([
            'clave' => 2,
            'nombre' => 'Municipio Sur',
            'oficina_id' => $this->otraOficina->id,
        ]);
        $this->tipo = EventoTipo::create([
            'nombre' => 'Culturales',
            'activo' => true,
        ]);
    }

    public function test_admin_can_create_and_deactivate_evento_tipo(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($admin)
            ->post(route('admin.evento-tipos.store'), [
                'nombre' => 'Deportivos',
                'activo' => '1',
            ])
            ->assertRedirect(route('admin.evento-tipos.index'));

        $tipo = EventoTipo::where('nombre', 'Deportivos')->firstOrFail();

        $this->actingAs($admin)
            ->put(route('admin.evento-tipos.update', $tipo), [
                'nombre' => 'Deportivos',
                'activo' => '0',
            ])
            ->assertRedirect(route('admin.evento-tipos.index'));

        $this->assertDatabaseHas('evento_tipos', [
            'id' => $tipo->id,
            'activo' => 0,
        ]);
    }

    public function test_delegado_can_create_evento_with_active_type(): void
    {
        $delegado = $this->delegado();

        $this->actingAs($delegado)
            ->get(route('eventos.create'))
            ->assertOk()
            ->assertSee('Nuevo evento');

        $this->actingAs($delegado)
            ->post(route('eventos.store'), $this->eventoPayload())
            ->assertRedirect(route('eventos.index'));

        $this->assertDatabaseHas('eventos', [
            'evento_tipo_id' => $this->tipo->id,
            'municipio_id' => $this->municipio->id,
            'oficina_id' => $this->oficina->id,
            'created_by' => $delegado->uuid,
            'rol_participacion' => Evento::ROL_ANFITRION,
            'total_asistentes' => 120,
        ]);
    }

    public function test_delegado_cannot_use_inactive_evento_tipo(): void
    {
        $delegado = $this->delegado();
        $inactive = EventoTipo::create([
            'nombre' => 'Inactivo',
            'activo' => false,
        ]);

        $this->actingAs($delegado)
            ->post(route('eventos.store'), $this->eventoPayload([
                'evento_tipo_id' => $inactive->id,
            ]))
            ->assertSessionHasErrors('evento_tipo_id');

        $this->assertDatabaseMissing('eventos', [
            'evento_tipo_id' => $inactive->id,
        ]);
    }

    public function test_delegado_cannot_select_municipio_from_another_office(): void
    {
        $delegado = $this->delegado();

        $this->actingAs($delegado)
            ->post(route('eventos.store'), $this->eventoPayload([
                'municipio_id' => $this->otroMunicipio->id,
            ]))
            ->assertSessionHasErrors('municipio_id');
    }

    public function test_delegado_can_edit_own_evento(): void
    {
        $delegado = $this->delegado();
        $evento = $this->eventoFor($delegado);
        $this->tipo->update(['activo' => false]);

        $this->actingAs($delegado)
            ->put(route('eventos.update', $evento), $this->eventoPayload([
                'descripcion' => 'Taller cultural actualizado',
                'total_asistentes' => 150,
            ]))
            ->assertRedirect(route('eventos.index'));

        $this->assertDatabaseHas('eventos', [
            'id' => $evento->id,
            'descripcion' => 'Taller cultural actualizado',
            'total_asistentes' => 150,
        ]);
    }

    public function test_delegado_cannot_edit_another_users_evento(): void
    {
        $owner = $this->delegado('owner@example.com');
        $other = $this->delegado('other@example.com');
        $evento = $this->eventoFor($owner);

        $this->actingAs($other)
            ->get(route('eventos.edit', $evento))
            ->assertForbidden();
    }

    public function test_admin_can_list_all_eventos(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $delegado = $this->delegado();
        $other = $this->delegado('other-admin-list@example.com', $this->otraOficina);

        $this->eventoFor($delegado, ['descripcion' => 'Evento norte']);
        $this->eventoFor($other, [
            'descripcion' => 'Evento sur',
            'municipio_id' => $this->otroMunicipio->id,
            'oficina_id' => $this->otraOficina->id,
        ]);

        $this->actingAs($admin)
            ->get(route('eventos.index'))
            ->assertOk()
            ->assertSee('Evento norte')
            ->assertSee('Evento sur');
    }

    public function test_capturista_cannot_access_eventos(): void
    {
        $capturista = User::factory()->create(['oficina_id' => $this->oficina->id]);
        $capturista->assignRole('capturista');

        $this->actingAs($capturista)
            ->get(route('eventos.index'))
            ->assertForbidden();
    }

    private function delegado(string $email = 'delegado@example.com', ?Oficina $oficina = null): User
    {
        $delegado = User::factory()->create([
            'email' => $email,
            'oficina_id' => ($oficina ?? $this->oficina)->id,
        ]);
        $delegado->assignRole('delegado');

        return $delegado;
    }

    private function eventoPayload(array $overrides = []): array
    {
        return array_merge([
            'evento_tipo_id' => $this->tipo->id,
            'municipio_id' => $this->municipio->id,
            'descripcion' => 'Taller cultural para jovenes',
            'lugar' => 'Auditorio municipal',
            'rol_participacion' => Evento::ROL_ANFITRION,
            'total_asistentes' => 120,
            'evidencia_url' => 'https://drive.google.com/example',
        ], $overrides);
    }

    private function eventoFor(User $user, array $overrides = []): Evento
    {
        return Evento::create(array_merge([
            'evento_tipo_id' => $this->tipo->id,
            'municipio_id' => $this->municipio->id,
            'oficina_id' => $user->oficina_id,
            'created_by' => $user->uuid,
            'descripcion' => 'Evento base',
            'lugar' => 'Plaza principal',
            'rol_participacion' => Evento::ROL_INVITADO,
            'total_asistentes' => 75,
            'evidencia_url' => null,
        ], $overrides));
    }
}
