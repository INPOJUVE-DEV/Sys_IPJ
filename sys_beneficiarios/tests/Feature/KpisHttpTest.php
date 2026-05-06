<?php

namespace Tests\Feature;

use App\Models\Beneficiario;
use App\Models\Evento;
use App\Models\EventoTipo;
use App\Models\Inscripcion;
use App\Models\Municipio;
use App\Models\Programa;
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

    public function test_admin_indicadores_routes_return_monthly_daily_structure(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $municipio = Municipio::create(['clave' => 30, 'nombre' => 'Indicadores']);
        $seccion = Seccion::create([
            'seccional' => '0030',
            'municipio_id' => $municipio->id,
            'distrito_local' => 'DL',
            'distrito_federal' => 'DF',
        ]);
        $programa = Programa::create([
            'nombre' => 'Programa Indicadores',
            'slug' => 'programa-indicadores',
            'tipo_periodo' => 'mensual',
            'renovable' => true,
            'activo' => true,
        ]);
        $eventoTipo = EventoTipo::create([
            'nombre' => 'Tipo Indicadores',
            'activo' => true,
        ]);

        $beneficiario = Beneficiario::create([
            'id' => (string) Str::uuid(),
            'folio_tarjeta' => 'IND-001',
            'nombre' => 'Lucia',
            'apellido_paterno' => 'Indicador',
            'apellido_materno' => 'Prueba',
            'curp' => 'IIPL000101MDFNCRA8',
            'fecha_nacimiento' => '2000-01-01',
            'edad' => 24,
            'sexo' => 'F',
            'discapacidad' => false,
            'id_ine' => 'INE-IND',
            'telefono' => '5511223344',
            'municipio_id' => $municipio->id,
            'seccion_id' => $seccion->id,
            'created_by' => $admin->uuid,
            'created_at' => now()->startOfMonth()->addDays(2),
            'updated_at' => now()->startOfMonth()->addDays(2),
        ]);

        Inscripcion::create([
            'id' => (string) Str::uuid(),
            'beneficiario_id' => $beneficiario->id,
            'programa_id' => $programa->id,
            'periodo' => now()->format('Y-m'),
            'estatus' => 'activa',
            'created_by' => $admin->uuid,
            'created_at' => now()->startOfMonth()->addDays(4),
            'updated_at' => now()->startOfMonth()->addDays(4),
        ]);

        Evento::create([
            'evento_tipo_id' => $eventoTipo->id,
            'municipio_id' => $municipio->id,
            'oficina_id' => null,
            'created_by' => $admin->uuid,
            'descripcion' => 'Evento indicadores',
            'lugar' => 'Plaza',
            'rol_participacion' => Evento::ROL_ANFITRION,
            'total_asistentes' => 10,
            'created_at' => now()->startOfMonth()->addDays(6),
            'updated_at' => now()->startOfMonth()->addDays(6),
        ]);

        $this->actingAs($admin)->get('/admin/indicadores')->assertOk();

        $this->actingAs($admin)
            ->get('/admin/indicadores/data?month='.now()->format('Y-m'))
            ->assertOk()
            ->assertJsonStructure([
                'range' => ['month', 'from', 'to'],
                'totals' => ['beneficiarios', 'inscripciones', 'eventos'],
                'daily' => ['labels', 'beneficiarios', 'inscripciones', 'eventos'],
            ])
            ->assertJsonPath('totals.beneficiarios', 1)
            ->assertJsonPath('totals.inscripciones', 1)
            ->assertJsonPath('totals.eventos', 1);
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
