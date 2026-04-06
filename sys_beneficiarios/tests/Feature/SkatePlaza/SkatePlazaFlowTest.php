<?php

namespace Tests\Feature\SkatePlaza;

use App\Models\Beneficiario;
use App\Models\Municipio;
use App\Models\Proteccion;
use App\Models\Seccion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SkatePlazaFlowTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $skateA;
    protected User $skateB;
    protected Municipio $municipio;
    protected Seccion $seccion;

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

        $this->municipio = Municipio::create([
            'clave' => 1,
            'nombre' => 'Municipio Skate',
        ]);

        $this->seccion = Seccion::create([
            'seccional' => '9999',
            'municipio_id' => $this->municipio->id,
            'distrito_local' => 'DL',
            'distrito_federal' => 'DF',
        ]);
    }

    public function test_skate_plaza_cannot_access_general_beneficiarios_index(): void
    {
        $this->actingAs($this->skateA)
            ->get(route('beneficiarios.index'))
            ->assertForbidden();
    }

    public function test_skate_plaza_can_search_beneficiary_by_curp_and_folio_with_exact_matching(): void
    {
        $beneficiario = $this->createBeneficiario(
            folioTarjeta: 'SKATE-001',
            curp: 'PEPJ000101HDFLRNA1',
        );

        $this->actingAs($this->skateA)
            ->getJson(route('skate-plaza.beneficiarios.search', [
                'tipo_busqueda' => 'curp',
                'valor' => 'PEPJ000101HDFLRNA1',
            ]))
            ->assertOk()
            ->assertJsonPath('id', $beneficiario->id)
            ->assertJsonPath('prestamo_activo', null);

        $this->actingAs($this->skateA)
            ->getJson(route('skate-plaza.beneficiarios.search', [
                'tipo_busqueda' => 'folio_tarjeta',
                'valor' => 'SKATE-001',
            ]))
            ->assertOk()
            ->assertJsonPath('folio_tarjeta', 'SKATE-001');

        $this->actingAs($this->skateA)
            ->getJson(route('skate-plaza.beneficiarios.search', [
                'tipo_busqueda' => 'folio_tarjeta',
                'valor' => 'SKATE',
            ]))
            ->assertNotFound();
    }

    public function test_skate_plaza_can_lend_and_return_a_protection(): void
    {
        $beneficiario = $this->createBeneficiario(
            folioTarjeta: 'SKATE-002',
            curp: 'PEPJ000101HDFLRNB2',
        );
        $proteccion = $this->createProteccion($this->skateA, 'Casco', 'CAS-100');

        $this->actingAs($this->skateA)
            ->post(route('skate-plaza.prestamos.store'), [
                'beneficiario_id' => $beneficiario->id,
                'proteccion_id' => $proteccion->id,
            ])
            ->assertRedirect(route('skate-plaza.home'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('protecciones', [
            'id' => $proteccion->id,
            'estatus' => Proteccion::STATUS_PRESTADA,
            'beneficiario_id' => $beneficiario->id,
        ]);
        $this->assertDatabaseHas('proteccion_movimientos', [
            'proteccion_id' => $proteccion->id,
            'tipo' => 'prestamo',
        ]);

        $this->actingAs($this->skateA)
            ->post(route('skate-plaza.prestamos.devolver', $proteccion))
            ->assertRedirect(route('skate-plaza.home'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('protecciones', [
            'id' => $proteccion->id,
            'estatus' => Proteccion::STATUS_DISPONIBLE,
            'beneficiario_id' => null,
        ]);
        $this->assertDatabaseHas('proteccion_movimientos', [
            'proteccion_id' => $proteccion->id,
            'tipo' => 'devolucion',
        ]);
    }

    public function test_skate_plaza_cannot_lend_foreign_or_duplicate_active_loans(): void
    {
        $beneficiario = $this->createBeneficiario(
            folioTarjeta: 'SKATE-003',
            curp: 'PEPJ000101HDFLRNC3',
        );
        $foreignProtection = $this->createProteccion($this->skateB, 'Casco', 'CAS-200');

        $this->actingAs($this->skateA)
            ->post(route('skate-plaza.prestamos.store'), [
                'beneficiario_id' => $beneficiario->id,
                'proteccion_id' => $foreignProtection->id,
            ])
            ->assertSessionHasErrors('proteccion_id');

        $ownedProtection = $this->createProteccion($this->skateA, 'Casco', 'CAS-201');

        $this->actingAs($this->skateB)
            ->post(route('skate-plaza.prestamos.store'), [
                'beneficiario_id' => $beneficiario->id,
                'proteccion_id' => $foreignProtection->id,
            ])
            ->assertRedirect(route('skate-plaza.home'))
            ->assertSessionHasNoErrors();

        $this->actingAs($this->skateA)
            ->post(route('skate-plaza.prestamos.store'), [
                'beneficiario_id' => $beneficiario->id,
                'proteccion_id' => $ownedProtection->id,
            ])
            ->assertSessionHasErrors('beneficiario_id');

        $this->actingAs($this->skateA)
            ->getJson(route('skate-plaza.beneficiarios.search', [
                'tipo_busqueda' => 'curp',
                'valor' => 'PEPJ000101HDFLRNC3',
            ]))
            ->assertOk()
            ->assertJsonPath('prestamo_activo.puede_devolver', false);
    }

    protected function createBeneficiario(string $folioTarjeta, string $curp): Beneficiario
    {
        return Beneficiario::create([
            'id' => (string) Str::uuid(),
            'folio_tarjeta' => $folioTarjeta,
            'nombre' => 'Mario',
            'apellido_paterno' => 'Skate',
            'apellido_materno' => 'Lopez',
            'curp' => $curp,
            'fecha_nacimiento' => '2000-01-01',
            'edad' => 24,
            'sexo' => 'M',
            'discapacidad' => false,
            'id_ine' => 'INE-SKATE',
            'telefono' => '5511111111',
            'municipio_id' => $this->municipio->id,
            'seccion_id' => $this->seccion->id,
            'created_by' => $this->admin->uuid,
        ]);
    }

    protected function createProteccion(User $user, string $tipo, string $numero): Proteccion
    {
        return Proteccion::create([
            'id' => (string) Str::uuid(),
            'tipo' => $tipo,
            'numero_inventario' => $numero,
            'estatus' => Proteccion::STATUS_DISPONIBLE,
            'usuario_uuid' => $user->uuid,
        ]);
    }
}
