<?php

namespace Tests\Feature;

use App\Models\Municipio;
use App\Models\Oficina;
use App\Models\Seccion;
use App\Models\Tarjeta;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $this->seed(\Database\Seeders\OficinaSeeder::class);
    }

    protected function officeAndSeccion(): array
    {
        $office = Oficina::where('tipo', Oficina::TIPO_DELEGACION)->orderBy('id')->firstOrFail();
        $mun = Municipio::updateOrCreate(
            ['clave' => 1],
            ['nombre' => 'Test', 'oficina_id' => $office->id]
        );
        $seccion = Seccion::updateOrCreate(
            ['seccional' => '0001'],
            ['municipio_id' => $mun->id, 'distrito_local' => 'DL', 'distrito_federal' => 'DF']
        );

        return [$office, $mun, $seccion];
    }

    protected function capturistaForOffice(Oficina $office): User
    {
        $user = User::factory()->create(['oficina_id' => $office->id]);
        $user->assignRole('capturista');

        return $user;
    }

    protected function createCard(Oficina $office, string $folio): Tarjeta
    {
        return Tarjeta::create([
            'id' => (string) Str::uuid(),
            'folio' => $folio,
            'estatus' => Tarjeta::STATUS_ASIGNADA_OFICINA,
            'oficina_id' => $office->id,
        ]);
    }

    protected function validPayload(array $overrides = []): array
    {
        [, $mun, $seccion] = $this->officeAndSeccion();
        return array_merge([
            'folio_tarjeta' => 'FT-'.rand(100,999),
            'nombre' => 'Juan',
            'apellido_paterno' => 'Perez',
            'apellido_materno' => 'Lopez',
            'curp' => 'PEPJ000101HDFLRNA1',
            'fecha_nacimiento' => '2000-01-01',
            'sexo' => 'M',
            'discapacidad' => '0',
            'id_ine' => 'INE123',
            'telefono' => '5512345678',
            'domicilio' => [
                'calle' => 'Calle',
                'numero_ext' => '1',
                'colonia' => 'Centro',
                'municipio_id' => $mun->id,
                'codigo_postal' => '01234',
                'seccional' => $seccion->seccional,
            ],
        ], $overrides);
    }

    public function test_invalid_curp_rejected(): void
    {
        [$office] = $this->officeAndSeccion();
        $u = $this->capturistaForOffice($office);
        $payload = $this->validPayload(['curp' => 'INVALIDA0000000000']);
        $this->actingAs($u)->post(route('beneficiarios.store'), $payload)->assertSessionHasErrors('curp');
    }

    public function test_invalid_phone_rejected(): void
    {
        [$office] = $this->officeAndSeccion();
        $u = $this->capturistaForOffice($office);
        $payload = $this->validPayload(['telefono' => '123']);
        $this->actingAs($u)->post(route('beneficiarios.store'), $payload)->assertSessionHasErrors('telefono');
    }

    public function test_unique_folio(): void
    {
        [$office] = $this->officeAndSeccion();
        $u = $this->capturistaForOffice($office);
        $this->createCard($office, 'FT-1');
        $p1 = $this->validPayload(['folio_tarjeta' => 'FT-1']);
        $p2 = $this->validPayload([
            'folio_tarjeta' => 'FT-1',
            'curp' => 'PEPJ000101HDFLRNB2',
            'id_ine' => 'INE999',
        ]);
        $this->actingAs($u)->post(route('beneficiarios.store'), $p1);
        $this->actingAs($u)->post(route('beneficiarios.store'), $p2)->assertSessionHasErrors('folio_tarjeta');
    }

    public function test_beneficiario_can_be_created_without_is_draft(): void
    {
        [$office] = $this->officeAndSeccion();
        $u = $this->capturistaForOffice($office);
        $this->createCard($office, 'FT-CREATE');
        $payload = $this->validPayload(['folio_tarjeta' => 'FT-CREATE']);

        $response = $this->actingAs($u)->post(route('beneficiarios.store'), $payload);

        $response->assertRedirect(route('beneficiarios.create'));

        $this->assertDatabaseHas('beneficiarios', [
            'folio_tarjeta' => 'FT-CREATE',
            'nombre' => 'Juan',
            'apellido_paterno' => 'Perez',
        ]);

        $benef = \App\Models\Beneficiario::where('folio_tarjeta', 'FT-CREATE')->first();
        $this->assertNotNull($benef);
        $this->assertSame('0001', optional($benef->seccion)->seccional);
        $this->assertNotNull($benef->municipio_id);

        $this->assertDatabaseHas('domicilios', [
            'beneficiario_id' => $benef->id,
            'calle' => 'Calle',
            'codigo_postal' => '01234',
        ]);
    }
}
