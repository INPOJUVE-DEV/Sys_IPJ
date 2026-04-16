<?php

namespace Tests\Feature\Admin;

use App\Models\Beneficiario;
use App\Models\Municipio;
use App\Models\Oficina;
use App\Models\Seccion;
use App\Models\Tarjeta;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InventoryModuleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $this->seed(\Database\Seeders\OficinaSeeder::class);
    }

    protected function createAdmin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        return $admin;
    }

    protected function createDelegado(Oficina $office): User
    {
        $delegado = User::factory()->create(['oficina_id' => $office->id]);
        $delegado->assignRole('delegado');

        return $delegado;
    }

    protected function createTarjeta(Oficina $office, string $folio): Tarjeta
    {
        return Tarjeta::create([
            'id' => (string) Str::uuid(),
            'folio' => $folio,
            'estatus' => Tarjeta::STATUS_ASIGNADA_OFICINA,
            'oficina_id' => $office->id,
        ]);
    }

    public function test_seeders_create_delegado_role_and_base_offices(): void
    {
        $this->assertNotNull(Role::where('name', 'delegado')->first());
        $this->assertSame(5, Oficina::count());
        $this->assertDatabaseHas('oficinas', ['nombre' => 'Central', 'tipo' => Oficina::TIPO_CENTRAL]);
        $this->assertDatabaseHas('oficinas', ['nombre' => 'Delegacion Altiplano', 'region' => 'Altiplano']);
    }

    public function test_delegado_only_sees_inventory_from_its_office(): void
    {
        $officeA = Oficina::where('tipo', Oficina::TIPO_DELEGACION)->orderBy('id')->firstOrFail();
        $officeB = Oficina::where('tipo', Oficina::TIPO_DELEGACION)->where('id', '!=', $officeA->id)->orderBy('id')->firstOrFail();
        $delegado = $this->createDelegado($officeA);

        $this->createTarjeta($officeA, 'DEL-100');
        $this->createTarjeta($officeB, 'DEL-200');

        $this->actingAs($delegado)
            ->get(route('delegacion.inventario.tarjetas.index'))
            ->assertOk();

        $this->assertSame(
            ['DEL-100'],
            Tarjeta::accessibleTo($delegado)->orderBy('folio')->pluck('folio')->all()
        );
    }

    public function test_admin_cannot_create_overlapping_vale_blocs(): void
    {
        $admin = $this->createAdmin();
        $office = Oficina::where('nombre', 'Central')->firstOrFail();

        $this->actingAs($admin)
            ->post(route('admin.inventario.vales.store'), [
                'folio_inicio' => 1000,
                'folio_fin' => 1999,
                'oficina_id' => $office->id,
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($admin)
            ->post(route('admin.inventario.vales.store'), [
                'folio_inicio' => 1500,
                'folio_fin' => 2499,
                'oficina_id' => $office->id,
            ])
            ->assertSessionHasErrors('folio_inicio');
    }

    public function test_backfill_command_creates_consumed_cards_for_historical_beneficiarios(): void
    {
        $admin = $this->createAdmin();
        $office = Oficina::where('tipo', Oficina::TIPO_DELEGACION)->orderBy('id')->firstOrFail();
        $mun = Municipio::create([
            'clave' => 10,
            'nombre' => 'Historico',
            'oficina_id' => $office->id,
        ]);
        $seccion = Seccion::create([
            'seccional' => '0010',
            'municipio_id' => $mun->id,
            'distrito_local' => 'DL',
            'distrito_federal' => 'DF',
        ]);

        $beneficiario = Beneficiario::create([
            'id' => (string) Str::uuid(),
            'folio_tarjeta' => 'HIST-001',
            'nombre' => 'Maria',
            'apellido_paterno' => 'Lopez',
            'apellido_materno' => 'Diaz',
            'curp' => 'LODM000101MDFLRNC4',
            'fecha_nacimiento' => '2000-01-01',
            'edad' => 24,
            'sexo' => 'F',
            'discapacidad' => false,
            'id_ine' => 'INE-HIST',
            'telefono' => '5511111111',
            'municipio_id' => $mun->id,
            'seccion_id' => $seccion->id,
            'created_by' => $admin->uuid,
        ]);

        $code = Artisan::call('inventario:backfill-tarjetas');

        $this->assertSame(0, $code);
        $beneficiario->refresh();
        $this->assertNotNull($beneficiario->tarjeta_id);
        $this->assertDatabaseHas('tarjetas', [
            'id' => $beneficiario->tarjeta_id,
            'folio' => 'HIST-001',
            'estatus' => Tarjeta::STATUS_CONSUMIDA,
            'beneficiario_id' => $beneficiario->id,
        ]);
    }
}
