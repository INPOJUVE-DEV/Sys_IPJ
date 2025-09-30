<?php

namespace Tests\Feature\S360;

use App\Models\Beneficiario;
use App\Models\Municipio;
use App\Models\Salud360Assignment;
use App\Models\Salud360Session;
use App\Models\User;
use Database\Seeders\Salud360RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SecurityAndUpdateTest extends TestCase
{
    use RefreshDatabase;

    private function makeBeneficiario(User $creator, Municipio $mun): Beneficiario
    {
        return Beneficiario::create([
            'id' => (string) Str::uuid(),
            'folio_tarjeta' => Str::upper(Str::random(10)),
            'nombre' => 'Luis',
            'apellido_paterno' => 'Nava',
            'apellido_materno' => 'Rocha',
            'curp' => Str::upper(Str::random(18)),
            'fecha_nacimiento' => '1991-02-02',
            'edad' => 34,
            'sexo' => 'M',
            'discapacidad' => false,
            'telefono' => '5551112233',
            'municipio_id' => $mun->id,
            'seccional' => '0003',
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

    public function test_psicologo_cannot_create_session_if_not_assigned(): void
    {
        $creator = User::factory()->create();
        $psico = User::factory()->create();
        $psico->assignRole('psicologo');
        $mun = Municipio::create(['clave' => 20, 'nombre' => 'Veinte']);
        $b = $this->makeBeneficiario($creator, $mun);

        $this->actingAs($psico)
            ->postJson('/s360/psico/sesiones', [
                'beneficiario_id' => $b->id,
                'session_date' => '2025-03-01',
            ])->assertForbidden();
    }

    public function test_session_number_is_consecutive(): void
    {
        $creator = User::factory()->create();
        $enc360 = User::factory()->create();
        $enc360->assignRole('encargado_360');
        $psico = User::factory()->create();
        $psico->assignRole('psicologo');
        $mun = Municipio::create(['clave' => 21, 'nombre' => 'Veintiuno']);
        $b = $this->makeBeneficiario($creator, $mun);
        // asignación
        Salud360Assignment::create([
            'beneficiario_id' => $b->id,
            'psicologo_id' => $psico->id,
            'assigned_by' => $enc360->id,
            'assigned_at' => now(),
            'active' => true,
        ]);

        // primera
        $this->actingAs($psico)
            ->postJson('/s360/psico/sesiones', [
                'beneficiario_id' => $b->id,
                'is_first' => true,
                'session_date' => '2025-04-01',
                'motivo_consulta' => 'Inicio',
                'riesgo_suicida' => false,
                'uso_sustancias' => false,
            ])->assertCreated();

        // continuidad
        $res = $this->actingAs($psico)
            ->postJson('/s360/psico/sesiones', [
                'beneficiario_id' => $b->id,
                'session_date' => '2025-04-10',
            ])->assertCreated();

        $payload = $res->json('session');
        $this->assertEquals(2, (int)($payload['session_number'] ?? 0));
    }

    public function test_update_session_allowed_for_encargado360_forbidden_for_psicologo(): void
    {
        $creator = User::factory()->create();
        $enc360 = User::factory()->create();
        $enc360->assignRole('encargado_360');
        $psico = User::factory()->create();
        $psico->assignRole('psicologo');
        $mun = Municipio::create(['clave' => 22, 'nombre' => 'Veintidos']);
        $b = $this->makeBeneficiario($creator, $mun);
        // asignación
        Salud360Assignment::create([
            'beneficiario_id' => $b->id,
            'psicologo_id' => $psico->id,
            'assigned_by' => $enc360->id,
            'assigned_at' => now(),
            'active' => true,
        ]);
        // sesión
        $s = Salud360Session::create([
            'beneficiario_id' => $b->id,
            'psicologo_id' => $psico->id,
            'session_date' => '2025-05-01',
            'session_number' => 1,
            'is_first' => true,
            'motivo_consulta' => 'Inicio',
            'riesgo_suicida' => false,
            'uso_sustancias' => false,
            'created_by' => $psico->id,
        ]);

        // Psicólogo no puede acceder a ruta de update (middleware rol)
        $this->actingAs($psico)
            ->putJson('/s360/enc360/sesiones/'.$s->id, [
                'session_date' => '2025-05-02',
            ])->assertForbidden();

        // Encargado 360 puede actualizar
        $this->actingAs($enc360)
            ->put('/s360/enc360/sesiones/'.$s->id, [
                'session_date' => '2025-05-03',
                'motivo_consulta' => 'Ajuste',
                'riesgo_suicida' => false,
                'uso_sustancias' => false,
            ])->assertRedirect();

        $this->assertDatabaseHas('salud360_sessions', [
            'id' => $s->id,
        ]);
        $raw = (string)\DB::table('salud360_sessions')->where('id', $s->id)->value('session_date');
        $this->assertEquals('2025-05-03', substr($raw, 0, 10));
    }
}
