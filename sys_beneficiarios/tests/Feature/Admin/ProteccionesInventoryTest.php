<?php

namespace Tests\Feature\Admin;

use App\Models\Proteccion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProteccionesInventoryTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $skateA;
    protected User $skateB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RoleSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->skateA = User::factory()->create();
        $this->skateA->assignRole('skate_plaza');

        $this->skateB = User::factory()->create();
        $this->skateB->assignRole('skate_plaza');
    }

    public function test_admin_can_create_batch_for_skate_plaza_user(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.inventario.protecciones.storeBatch'), [
                'tipo' => 'Casco',
                'usuario_uuid' => $this->skateA->uuid,
                'numeros_inventario' => "CAS-001\nCAS-002",
            ])
            ->assertRedirect(route('admin.inventario.protecciones.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('protecciones', [
            'numero_inventario' => 'CAS-001',
            'tipo' => 'Casco',
            'usuario_uuid' => $this->skateA->uuid,
            'estatus' => Proteccion::STATUS_DISPONIBLE,
        ]);
        $this->assertDatabaseHas('protecciones', [
            'numero_inventario' => 'CAS-002',
            'tipo' => 'Casco',
            'usuario_uuid' => $this->skateA->uuid,
            'estatus' => Proteccion::STATUS_DISPONIBLE,
        ]);
        $this->assertDatabaseCount('proteccion_movimientos', 2);
    }

    public function test_admin_can_transfer_and_toggle_protection_status(): void
    {
        $proteccion = Proteccion::create([
            'id' => (string) Str::uuid(),
            'tipo' => 'Codera',
            'numero_inventario' => 'COD-001',
            'estatus' => Proteccion::STATUS_DISPONIBLE,
            'usuario_uuid' => $this->skateA->uuid,
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.inventario.protecciones.transfer', $proteccion), [
                'usuario_uuid' => $this->skateB->uuid,
            ])
            ->assertRedirect(route('admin.inventario.protecciones.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('protecciones', [
            'id' => $proteccion->id,
            'usuario_uuid' => $this->skateB->uuid,
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.inventario.protecciones.status', $proteccion), [
                'estatus' => Proteccion::STATUS_INACTIVA,
            ])
            ->assertRedirect(route('admin.inventario.protecciones.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('protecciones', [
            'id' => $proteccion->id,
            'estatus' => Proteccion::STATUS_INACTIVA,
        ]);
    }
}
