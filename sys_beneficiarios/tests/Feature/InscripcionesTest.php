<?php

namespace Tests\Feature;

use App\Models\Beneficiario;
use App\Models\Inscripcion;
use App\Models\Municipio;
use App\Models\Oficina;
use App\Models\Programa;
use App\Models\Seccion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class InscripcionesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $this->seed(\Database\Seeders\OficinaSeeder::class);
    }

    protected function makeOfficeAndSeccion(int $clave = 1, string $seccional = '12345'): array
    {
        $office = Oficina::where('tipo', Oficina::TIPO_DELEGACION)->orderBy('id')->firstOrFail();
        $mun = Municipio::updateOrCreate(
            ['clave' => $clave],
            ['nombre' => 'Test '.$clave, 'oficina_id' => $office->id]
        );
        $seccion = Seccion::updateOrCreate(
            ['seccional' => $seccional],
            ['municipio_id' => $mun->id, 'distrito_local' => 'DL', 'distrito_federal' => 'DF']
        );

        return [$office, $seccion];
    }

    protected function makeCapturista(Oficina $office): User
    {
        $user = User::factory()->create(['oficina_id' => $office->id]);
        $user->assignRole('capturista');

        return $user;
    }

    protected function basePayload(Programa $programa, Seccion $seccion, array $overrides = []): array
    {
        return array_merge([
            'programa_id' => $programa->id,
            'periodo' => '2026-01',
            'estatus' => 'inscrito',
            'folio_tarjeta' => null,
            'nombre' => 'Ana',
            'apellido_paterno' => 'Lopez',
            'apellido_materno' => 'Diaz',
            'curp' => 'LODA000101MDFLRNA2',
            'fecha_nacimiento' => '2000-01-01',
            'sexo' => 'F',
            'discapacidad' => '0',
            'id_ine' => $seccion->seccional.'0001122334455',
            'telefono' => '5512345678',
            'domicilio' => [
                'calle' => 'Calle',
                'numero_ext' => '1',
                'colonia' => 'Centro',
                'municipio_id' => $seccion->municipio_id,
                'codigo_postal' => '01234',
                'seccional' => $seccion->seccional,
            ],
        ], $overrides);
    }

    public function test_capturista_can_register_inscripcion_without_folio(): void
    {
        [$office, $seccion] = $this->makeOfficeAndSeccion();
        $user = $this->makeCapturista($office);
        $programa = Programa::create([
            'nombre' => 'Cabina de grabacion',
            'slug' => 'cabina-de-grabacion',
            'tipo_periodo' => 'mensual',
            'renovable' => false,
            'activo' => true,
        ]);

        $payload = $this->basePayload($programa, $seccion, ['folio_tarjeta' => null]);

        $this->actingAs($user)
            ->post(route('inscripciones.store'), $payload)
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('inscripciones.index'));

        $this->assertDatabaseHas('beneficiarios', [
            'curp' => 'LODA000101MDFLRNA2',
            'folio_tarjeta' => null,
        ]);

        $this->assertDatabaseHas('inscripciones', [
            'programa_id' => $programa->id,
            'periodo' => '2026-01',
            'estatus' => 'inscrito',
        ]);
    }

    public function test_duplicate_inscripcion_is_rejected(): void
    {
        [$office, $seccion] = $this->makeOfficeAndSeccion();
        $user = $this->makeCapturista($office);
        $programa = Programa::create([
            'nombre' => 'Club de tareas',
            'slug' => 'club-de-tareas',
            'tipo_periodo' => 'mensual',
            'renovable' => true,
            'activo' => true,
        ]);

        $payload = $this->basePayload($programa, $seccion);

        $this->actingAs($user)
            ->post(route('inscripciones.store'), $payload)
            ->assertSessionHasNoErrors();

        $this->actingAs($user)
            ->post(route('inscripciones.store'), $payload)
            ->assertSessionHasErrors('periodo');
    }

    public function test_capturista_can_store_manual_card_number_during_inscripcion(): void
    {
        [$office, $seccion] = $this->makeOfficeAndSeccion(2, '23456');
        $user = $this->makeCapturista($office);

        $programa = Programa::create([
            'nombre' => 'Laboratorio creativo',
            'slug' => 'laboratorio-creativo',
            'tipo_periodo' => 'mensual',
            'renovable' => false,
            'activo' => true,
        ]);

        $payload = $this->basePayload($programa, $seccion, [
            'folio_tarjeta' => 'TAR-0002',
            'curp' => 'LODA000101MDFLRNB3',
            'id_ine' => '23456000999888777',
        ]);

        $this->actingAs($user)
            ->post(route('inscripciones.store'), $payload)
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('inscripciones.index'));

        $beneficiario = Beneficiario::where('curp', 'LODA000101MDFLRNB3')->first();
        $this->assertNotNull($beneficiario);
        $this->assertSame('TAR-0002', $beneficiario->folio_tarjeta);
        $this->assertNull($beneficiario->tarjeta_id);
    }

    public function test_dashboard_kpis_use_periodo(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        [, $seccion] = $this->makeOfficeAndSeccion();
        $programa = Programa::create([
            'nombre' => 'Clases de guitarra',
            'slug' => 'clases-de-guitarra',
            'tipo_periodo' => 'mensual',
            'renovable' => true,
            'activo' => true,
        ]);

        $beneficiario = Beneficiario::create([
            'id' => (string) Str::uuid(),
            'folio_tarjeta' => null,
            'nombre' => 'Luis',
            'apellido_paterno' => 'Perez',
            'apellido_materno' => 'Cruz',
            'curp' => 'PECL000101HDFLRNA3',
            'fecha_nacimiento' => '2000-01-01',
            'edad' => 24,
            'sexo' => 'M',
            'discapacidad' => false,
            'id_ine' => 'INE456',
            'telefono' => '5512345678',
            'municipio_id' => $seccion->municipio_id,
            'seccion_id' => $seccion->id,
            'created_by' => $admin->uuid,
        ]);

        Inscripcion::create([
            'id' => (string) Str::uuid(),
            'beneficiario_id' => $beneficiario->id,
            'programa_id' => $programa->id,
            'periodo' => '2026-01',
            'estatus' => 'inscrito',
            'created_by' => $admin->uuid,
        ]);
        Inscripcion::create([
            'id' => (string) Str::uuid(),
            'beneficiario_id' => $beneficiario->id,
            'programa_id' => $programa->id,
            'periodo' => '2026-02',
            'estatus' => 'inscrito',
            'created_by' => $admin->uuid,
        ]);

        $this->actingAs($admin)
            ->get(route('inscripciones.kpis', ['from' => '2026-01', 'to' => '2026-02', 'programa_id' => $programa->id]))
            ->assertOk()
            ->assertJsonPath('totals.total', 2)
            ->assertJsonPath('monthly.total', 2);
    }
}
