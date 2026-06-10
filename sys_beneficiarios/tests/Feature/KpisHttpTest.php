<?php

namespace Tests\Feature;

use App\Models\Beneficiario;
use App\Models\Municipio;
use App\Models\Proteccion;
use App\Models\ProteccionMovimiento;
use App\Models\Seccion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class KpisHttpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RoleSeeder::class);
    }

    public function test_admin_kpis_structure(): void
    {
        $admin = User::factory()->create(); $admin->assignRole('admin');
        $this->actingAs($admin)->get('/admin/kpis')
            ->assertOk()
            ->assertJsonStructure([
                'totals'=>['total'],
                'week' => ['labels','data','total'],
                'last30Days' => ['labels','data','total'],
                'skatePlaza' => [
                    'currentMonth',
                    'monthly' => ['labels', 'data', 'total'],
                ],
            ]);
    }

    public function test_capturista_kpis_structure(): void
    {
        $mun = Municipio::create(['clave'=>1,'nombre'=>'Test']);
        $cap = User::factory()->create(); $cap->assignRole('capturista');
        $seccion = \App\Models\Seccion::create([
            'seccional' => '0001',
            'municipio_id' => $mun->id,
            'distrito_local' => 'DL',
            'distrito_federal' => 'DF',
        ]);
        Beneficiario::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'folio_tarjeta' => 'FT-HTTP',
            'nombre' => 'Juan', 'apellido_paterno'=>'P', 'apellido_materno'=>'L',
            'curp' => 'PEPJ000101HDFLRNA1',
            'fecha_nacimiento' => '2000-01-01', 'sexo'=>'M', 'discapacidad'=>false,
            'id_ine' => 'INE', 'telefono'=>'5512345678', 'municipio_id'=>$mun->id,
            'seccion_id'=>$seccion->id,'created_by'=> $cap->uuid,
        ]);
        $this->actingAs($cap)->get('/capturista/kpis')
            ->assertOk()
            ->assertJsonStructure(['today','week','last30Days','ultimos','series'=>['labels','data']]);
    }

    public function test_admin_kpis_count_only_skate_plaza_loan_movements(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $skate = User::factory()->create();
        $skate->assignRole('skate_plaza');

        $mun = Municipio::create(['clave' => 2, 'nombre' => 'Skate KPI']);
        $seccion = Seccion::create([
            'seccional' => '0002',
            'municipio_id' => $mun->id,
            'distrito_local' => 'DL',
            'distrito_federal' => 'DF',
        ]);
        $beneficiario = Beneficiario::create([
            'id' => (string) Str::uuid(),
            'folio_tarjeta' => 'KPI-001',
            'nombre' => 'Karla',
            'apellido_paterno' => 'Lopez',
            'apellido_materno' => 'Diaz',
            'curp' => 'LODK000101MDFLRNA1',
            'fecha_nacimiento' => '2000-01-01',
            'edad' => 24,
            'sexo' => 'F',
            'discapacidad' => false,
            'id_ine' => 'INE-KPI',
            'telefono' => '5512345678',
            'municipio_id' => $mun->id,
            'seccion_id' => $seccion->id,
            'created_by' => $admin->uuid,
        ]);
        $proteccion = Proteccion::create([
            'id' => (string) Str::uuid(),
            'tipo' => 'Casco',
            'numero_inventario' => 'KPI-CAS-01',
            'estatus' => Proteccion::STATUS_DISPONIBLE,
            'usuario_uuid' => $skate->uuid,
        ]);

        ProteccionMovimiento::create([
            'id' => (string) Str::uuid(),
            'proteccion_id' => $proteccion->id,
            'tipo' => 'prestamo',
            'actor_uuid' => $skate->uuid,
            'to_usuario_uuid' => $skate->uuid,
            'beneficiario_id' => $beneficiario->id,
        ]);
        ProteccionMovimiento::create([
            'id' => (string) Str::uuid(),
            'proteccion_id' => $proteccion->id,
            'tipo' => 'devolucion',
            'actor_uuid' => $skate->uuid,
            'to_usuario_uuid' => $skate->uuid,
            'beneficiario_id' => $beneficiario->id,
        ]);

        $this->actingAs($admin)->get('/admin/kpis')
            ->assertOk()
            ->assertJsonPath('skatePlaza.currentMonth', 1)
            ->assertJsonPath('skatePlaza.monthly.total', 1);
    }
}
