<?php

namespace Tests\Feature\S360;

use App\Models\Beneficiario;
use App\Models\Municipio;
use App\Models\Salud360Assignment;
use App\Models\User;
use Database\Seeders\Salud360RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AssignmentFeatureTest extends TestCase
{
    use RefreshDatabase;

    private function makeBeneficiario(User $creator, Municipio $mun): Beneficiario
    {
        return Beneficiario::create([
            'id' => (string) Str::uuid(),
            'folio_tarjeta' => Str::upper(Str::random(10)),
            'nombre' => 'Ana',
            'apellido_paterno' => 'García',
            'apellido_materno' => 'Luna',
            'curp' => Str::upper(Str::random(18)),
            'fecha_nacimiento' => '1992-05-10',
            'edad' => 33,
            'sexo' => 'F',
            'discapacidad' => false,
            'telefono' => '5554443322',
            'municipio_id' => $mun->id,
            'seccional' => '0002',
            'distrito_local' => '01',
            'distrito_federal' => '01',
            'created_by' => $creator->uuid,
        ]);
    }

    public function setUp(): void
    {
        parent::setUp();
        $this->seed(Salud360RolesSeeder::class);
    }

    public function test_assign_only_by_encargado360(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $creator = User::factory()->create();
        $enc360 = User::factory()->create();
        $enc360->assignRole('encargado_360');
        $psico = User::factory()->create();
        $psico->assignRole('psicologo');
        $mun = Municipio::create(['clave' => 9, 'nombre' => 'Nueve']);
        $b = $this->makeBeneficiario($creator, $mun);

        // Admin no puede por middleware de rol
        $this->actingAs($admin)
            ->postJson('/s360/enc360/assign', [
                'beneficiario_id' => $b->id,
                'psicologo_id' => $psico->id,
            ])->assertForbidden();

        // Encargado 360 sí
        $this->actingAs($enc360)
            ->postJson('/s360/enc360/assign', [
                'beneficiario_id' => $b->id,
                'psicologo_id' => $psico->id,
            ])->assertCreated();
    }

    public function test_no_two_active_assignments(): void
    {
        $creator = User::factory()->create();
        $enc360 = User::factory()->create();
        $enc360->assignRole('encargado_360');
        $psico1 = User::factory()->create();
        $psico1->assignRole('psicologo');
        $psico2 = User::factory()->create();
        $psico2->assignRole('psicologo');
        $mun = Municipio::create(['clave' => 10, 'nombre' => 'Diez']);
        $b = $this->makeBeneficiario($creator, $mun);

        $this->actingAs($enc360)
            ->postJson('/s360/enc360/assign', [
                'beneficiario_id' => $b->id,
                'psicologo_id' => $psico1->id,
            ])->assertCreated();

        // Intentar asignar de nuevo (activo existe)
        $this->actingAs($enc360)
            ->postJson('/s360/enc360/assign', [
                'beneficiario_id' => $b->id,
                'psicologo_id' => $psico2->id,
            ])->assertStatus(422);

        $this->assertDatabaseHas('salud360_assignments', [
            'beneficiario_id' => $b->id,
            'psicologo_id' => $psico1->id,
            'active' => 1,
        ]);
    }
}

