<?php

namespace Tests\Feature\Admin;

use App\Models\Beneficiario;
use App\Models\Municipio;
use App\Models\Oficina;
use App\Models\Seccion;
use App\Models\Tarjeta;
use App\Models\User;
use App\Services\TarjetaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
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

    protected function createCapturista(Oficina $office): User
    {
        $capturista = User::factory()->create(['oficina_id' => $office->id]);
        $capturista->assignRole('capturista');

        return $capturista;
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

    public function test_delegado_assigns_cards_to_municipio_without_user(): void
    {
        $office = Oficina::where('tipo', Oficina::TIPO_DELEGACION)->orderBy('id')->firstOrFail();
        $delegado = $this->createDelegado($office);
        $municipio = Municipio::create([
            'clave' => 101,
            'nombre' => 'Municipio Asignable',
            'region' => $office->region,
            'oficina_id' => $office->id,
        ]);
        $tarjeta = $this->createTarjeta($office, 'MUN-001');

        $this->actingAs($delegado)
            ->post(route('delegacion.inventario.tarjetas.assignRange'), [
                'cantidad' => 1,
                'municipio_id' => $municipio->id,
                'observaciones' => 'Asignacion por municipio',
            ])
            ->assertRedirect(route('delegacion.inventario.tarjetas.index'))
            ->assertSessionHasNoErrors();

        $tarjeta->refresh();
        $this->assertSame($municipio->id, $tarjeta->municipio_id);
        $this->assertNull($tarjeta->usuario_uuid);
        $this->assertSame(Tarjeta::STATUS_ASIGNADA_OFICINA, $tarjeta->estatus);
        $this->assertDatabaseHas('tarjeta_movimientos', [
            'tarjeta_id' => $tarjeta->id,
            'tipo' => 'asignacion_municipio',
        ]);
    }

    public function test_delegado_cannot_assign_cards_to_municipio_from_another_office(): void
    {
        $officeA = Oficina::where('tipo', Oficina::TIPO_DELEGACION)->orderBy('id')->firstOrFail();
        $officeB = Oficina::where('tipo', Oficina::TIPO_DELEGACION)->where('id', '!=', $officeA->id)->orderBy('id')->firstOrFail();
        $delegado = $this->createDelegado($officeA);
        $municipio = Municipio::create([
            'clave' => 102,
            'nombre' => 'Municipio Ajeno',
            'region' => $officeB->region,
            'oficina_id' => $officeB->id,
        ]);
        $this->createTarjeta($officeA, 'MUN-002');

        $this->actingAs($delegado)
            ->post(route('delegacion.inventario.tarjetas.assignRange'), [
                'cantidad' => 1,
                'municipio_id' => $municipio->id,
            ])
            ->assertSessionHasErrors('municipio_id');
    }

    public function test_admin_can_assign_cards_to_any_municipio_with_office_stock(): void
    {
        $admin = $this->createAdmin();
        $office = Oficina::where('tipo', Oficina::TIPO_DELEGACION)->orderBy('id')->firstOrFail();
        $municipio = Municipio::create([
            'clave' => 103,
            'nombre' => 'Municipio Admin',
            'region' => $office->region,
            'oficina_id' => $office->id,
        ]);
        $tarjeta = $this->createTarjeta($office, 'MUN-003');

        $this->actingAs($admin)
            ->post(route('admin.inventario.tarjetas.assignRange'), [
                'cantidad' => 1,
                'municipio_id' => $municipio->id,
            ])
            ->assertRedirect(route('admin.inventario.tarjetas.index'))
            ->assertSessionHasNoErrors();

        $tarjeta->refresh();
        $this->assertSame($municipio->id, $tarjeta->municipio_id);
        $this->assertNull($tarjeta->usuario_uuid);
    }

    public function test_capturista_consumes_card_from_beneficiario_municipio_without_user_assignment(): void
    {
        $office = Oficina::where('tipo', Oficina::TIPO_DELEGACION)->orderBy('id')->firstOrFail();
        $capturista = $this->createCapturista($office);
        [$municipio, $beneficiario] = $this->municipioBeneficiario($office, $capturista, 104);
        $tarjeta = $this->createTarjeta($office, 'MUN-004');
        $tarjeta->forceFill(['municipio_id' => $municipio->id])->save();

        $consumed = app(TarjetaService::class)->consumeNextAvailable($capturista, $beneficiario, $municipio->id);

        $this->assertSame($tarjeta->id, $consumed->id);
        $this->assertSame(Tarjeta::STATUS_CONSUMIDA, $consumed->estatus);
        $this->assertSame($capturista->uuid, $consumed->usuario_uuid);
    }

    public function test_capturista_consumes_regional_card_when_municipio_stock_is_empty(): void
    {
        $office = Oficina::where('tipo', Oficina::TIPO_DELEGACION)->orderBy('id')->firstOrFail();
        $capturista = $this->createCapturista($office);
        [$municipio, $beneficiario] = $this->municipioBeneficiario($office, $capturista, 105);
        $tarjeta = $this->createTarjeta($office, 'MUN-005');

        $consumed = app(TarjetaService::class)->consumeNextAvailable($capturista, $beneficiario, $municipio->id);

        $this->assertSame($tarjeta->id, $consumed->id);
        $this->assertSame($municipio->id, $consumed->municipio_id);
        $this->assertSame($capturista->uuid, $consumed->usuario_uuid);
    }

    public function test_capturista_gets_error_when_municipio_and_region_stock_are_empty(): void
    {
        $office = Oficina::where('tipo', Oficina::TIPO_DELEGACION)->orderBy('id')->firstOrFail();
        $capturista = $this->createCapturista($office);
        [$municipio, $beneficiario] = $this->municipioBeneficiario($office, $capturista, 106);

        $this->expectException(ValidationException::class);

        app(TarjetaService::class)->consumeNextAvailable($capturista, $beneficiario, $municipio->id);
    }

    public function test_historical_user_assigned_cards_can_be_consumed_by_municipio(): void
    {
        $office = Oficina::where('tipo', Oficina::TIPO_DELEGACION)->orderBy('id')->firstOrFail();
        $capturista = $this->createCapturista($office);
        $other = $this->createCapturista($office);
        [$municipio, $beneficiario] = $this->municipioBeneficiario($office, $capturista, 107);
        $tarjeta = $this->createTarjeta($office, 'MUN-006');
        $tarjeta->forceFill([
            'municipio_id' => $municipio->id,
            'usuario_uuid' => $other->uuid,
            'estatus' => Tarjeta::STATUS_ASIGNADA_USUARIO,
        ])->save();

        $consumed = app(TarjetaService::class)->consumeNextAvailable($capturista, $beneficiario, $municipio->id);

        $this->assertSame($tarjeta->id, $consumed->id);
        $this->assertSame($capturista->uuid, $consumed->usuario_uuid);
    }

    public function test_historical_user_assigned_card_can_be_found_by_folio_inside_same_region(): void
    {
        $office = Oficina::where('tipo', Oficina::TIPO_DELEGACION)->orderBy('id')->firstOrFail();
        $capturista = $this->createCapturista($office);
        $other = $this->createCapturista($office);
        $tarjeta = $this->createTarjeta($office, 'MUN-FOLIO-001');
        $tarjeta->forceFill([
            'usuario_uuid' => $other->uuid,
            'estatus' => Tarjeta::STATUS_ASIGNADA_USUARIO,
        ])->save();

        $found = app(TarjetaService::class)->findConsumableByFolio($tarjeta->folio, $capturista);

        $this->assertSame($tarjeta->id, $found?->id);
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

    protected function municipioBeneficiario(Oficina $office, User $creator, int $clave): array
    {
        $municipio = Municipio::create([
            'clave' => $clave,
            'nombre' => "Municipio {$clave}",
            'region' => $office->region,
            'oficina_id' => $office->id,
        ]);
        $seccion = Seccion::create([
            'seccional' => (string) $clave,
            'municipio_id' => $municipio->id,
            'distrito_local' => 'DL',
            'distrito_federal' => 'DF',
        ]);
        $beneficiario = Beneficiario::create([
            'id' => (string) Str::uuid(),
            'folio_tarjeta' => "BEN-{$clave}",
            'nombre' => 'Persona',
            'apellido_paterno' => 'Prueba',
            'apellido_materno' => 'Municipio',
            'curp' => "PUMU000101HSP{$clave}A",
            'fecha_nacimiento' => '2000-01-01',
            'edad' => 24,
            'sexo' => 'M',
            'discapacidad' => false,
            'id_ine' => "INE-{$clave}",
            'telefono' => '5511111111',
            'municipio_id' => $municipio->id,
            'seccion_id' => $seccion->id,
            'created_by' => $creator->uuid,
        ]);

        return [$municipio, $beneficiario];
    }
}
