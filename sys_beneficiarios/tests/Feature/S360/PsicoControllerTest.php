<?php

namespace Tests\Feature\S360;

use App\Models\Beneficiario;
use App\Models\Municipio;
use App\Models\Salud360Assignment;
use App\Models\Salud360Session;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\Salud360RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PsicoControllerTest extends TestCase
{
    use RefreshDatabase;

    private function makeBeneficiario(User $creator, Municipio $mun): Beneficiario
    {
        return Beneficiario::create([
            'id' => (string) Str::uuid(),
            'folio_tarjeta' => Str::upper(Str::random(10)),
            'nombre' => 'Juan',
            'apellido_paterno' => 'Pérez',
            'apellido_materno' => 'López',
            'curp' => Str::upper(Str::random(18)),
            'fecha_nacimiento' => '1990-01-15',
            'edad' => 34,
            'sexo' => 'M',
            'discapacidad' => false,
            'telefono' => '5555555555',
            'municipio_id' => $mun->id,
            'seccional' => '0001',
            'distrito_local' => '01',
            'distrito_federal' => '01',
            'created_by' => $creator->uuid,
        ]);
    }

    private function makePsicologo(string $email = 'psico@example.com'): User
    {
        $u = User::factory()->create(['email' => $email]);
        $u->assignRole('psicologo');
        return $u;
    }

    public function setUp(): void
    {
        parent::setUp();
        $this->seed(Salud360RolesSeeder::class);
    }

    public function test_pacientes_lista_solo_asignados_al_psicologo(): void
    {
        $psico1 = $this->makePsicologo('p1@example.com');
        $psico2 = $this->makePsicologo('p2@example.com');
        $creator = User::factory()->create();
        $mun = Municipio::create(['clave' => 1, 'nombre' => 'Uno']);

        $b1 = $this->makeBeneficiario($creator, $mun);
        $b2 = $this->makeBeneficiario($creator, $mun);
        $b3 = $this->makeBeneficiario($creator, $mun);

        Salud360Assignment::create([
            'beneficiario_id' => $b1->id,
            'psicologo_id' => $psico1->id,
            'assigned_by' => $creator->id,
            'assigned_at' => now(),
            'active' => true,
        ]);
        Salud360Assignment::create([
            'beneficiario_id' => $b2->id,
            'psicologo_id' => $psico2->id,
            'assigned_by' => $creator->id,
            'assigned_at' => now(),
            'active' => true,
        ]);

        $this->actingAs($psico1)
            ->get('/s360/psico/pacientes')
            ->assertOk()
            ->assertJsonCount(1, 'items')
            ->assertJsonFragment(['id' => $b1->id]);
    }

    public function test_show_paciente_header(): void
    {
        $psico = $this->makePsicologo();
        $creator = User::factory()->create();
        $mun = Municipio::create(['clave' => 2, 'nombre' => 'Dos']);
        $b = $this->makeBeneficiario($creator, $mun);

        Salud360Assignment::create([
            'beneficiario_id' => $b->id,
            'psicologo_id' => $psico->id,
            'assigned_by' => $creator->id,
            'assigned_at' => now(),
            'active' => true,
        ]);

        $this->actingAs($psico)
            ->get('/s360/psico/paciente/'.$b->id)
            ->assertOk()
            ->assertJsonFragment([
                'id' => $b->id,
                'nombre' => 'Juan Pérez López',
                'telefono' => '5555555555',
            ])
            ->assertJsonStructure(['edad']);
    }

    public function test_store_sesion_first_and_continuity_with_rules(): void
    {
        $psico = $this->makePsicologo();
        $creator = User::factory()->create();
        $mun = Municipio::create(['clave' => 3, 'nombre' => 'Tres']);
        $b = $this->makeBeneficiario($creator, $mun);
        Salud360Assignment::create([
            'beneficiario_id' => $b->id,
            'psicologo_id' => $psico->id,
            'assigned_by' => $creator->id,
            'assigned_at' => now(),
            'active' => true,
        ]);

        // Falla si falta motivo/flags en primera
        $this->actingAs($psico)
            ->postJson('/s360/psico/sesiones', [
                'beneficiario_id' => $b->id,
                'is_first' => true,
                'session_date' => '2025-01-10',
            ])
            ->assertStatus(422);

        // Primera válida
        $this->actingAs($psico)
            ->postJson('/s360/psico/sesiones', [
                'beneficiario_id' => $b->id,
                'is_first' => true,
                'session_date' => '2025-01-10',
                'motivo_consulta' => 'Ansiedad',
                'riesgo_suicida' => false,
                'uso_sustancias' => false,
            ])
            ->assertCreated()
            ->assertJsonPath('session.session_number', 1);

        // Misma fecha no permitida
        $this->actingAs($psico)
            ->postJson('/s360/psico/sesiones', [
                'beneficiario_id' => $b->id,
                'session_date' => '2025-01-10',
            ])
            ->assertStatus(422);

        // Continuidad válida y next_session_date posterior
        $this->actingAs($psico)
            ->postJson('/s360/psico/sesiones', [
                'beneficiario_id' => $b->id,
                'session_date' => '2025-01-20',
                'next_session_date' => '2025-01-27',
                'next_objective' => 'Seguimiento',
                'notes' => 'Mejora',
            ])
            ->assertCreated()
            ->assertJsonPath('session.session_number', 2);
    }

    public function test_historial_desc(): void
    {
        $psico = $this->makePsicologo();
        $creator = User::factory()->create();
        $mun = Municipio::create(['clave' => 4, 'nombre' => 'Cuatro']);
        $b = $this->makeBeneficiario($creator, $mun);
        Salud360Assignment::create([
            'beneficiario_id' => $b->id,
            'psicologo_id' => $psico->id,
            'assigned_by' => $creator->id,
            'assigned_at' => now(),
            'active' => true,
        ]);

        $this->actingAs($psico)
            ->postJson('/s360/psico/sesiones', [
                'beneficiario_id' => $b->id,
                'is_first' => true,
                'session_date' => '2025-02-01',
                'motivo_consulta' => 'Ansiedad',
                'riesgo_suicida' => false,
                'uso_sustancias' => false,
            ])->assertCreated();

        $this->actingAs($psico)
            ->postJson('/s360/psico/sesiones', [
                'beneficiario_id' => $b->id,
                'session_date' => '2025-02-10',
            ])->assertCreated();

        $res = $this->actingAs($psico)
            ->get('/s360/psico/sesiones/'.$b->id)
            ->assertOk()
            ->json('items');

        $this->assertTrue(Carbon::parse($res[0]['session_date'])->gte(Carbon::parse($res[1]['session_date'])));
    }
}

