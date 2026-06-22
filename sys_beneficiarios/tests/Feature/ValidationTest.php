<?php

namespace Tests\Feature;

use App\Models\Municipio;
use App\Models\Oficina;
use App\Models\Seccion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            ['seccional' => '12345'],
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

    protected function validPayload(array $overrides = []): array
    {
        [, $mun, $seccion] = $this->officeAndSeccion();
        return array_merge([
            'nombre' => 'Juan',
            'apellido_paterno' => 'Perez',
            'apellido_materno' => 'Lopez',
            'curp' => 'PEPJ000101HDFLRNA1',
            'fecha_nacimiento' => '2000-01-01',
            'sexo' => 'M',
            'discapacidad' => '0',
            'id_ine' => '123450001122334455',
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

    public function test_seccional_is_resolved_from_id_ine_without_visible_field(): void
    {
        [$office] = $this->officeAndSeccion();
        $u = $this->capturistaForOffice($office);
        $payload = $this->validPayload([
            'domicilio' => [
                'calle' => 'Calle',
                'numero_ext' => '1',
                'colonia' => 'Centro',
                'municipio_id' => Municipio::firstOrFail()->id,
                'codigo_postal' => '01234',
            ],
        ]);

        $response = $this->actingAs($u)->post(route('beneficiarios.store'), $payload);

        $response->assertRedirect(route('beneficiarios.create'));
        $this->assertDatabaseHas('beneficiarios', [
            'curp' => 'PEPJ000101HDFLRNA1',
        ]);

        $benef = \App\Models\Beneficiario::where('curp', 'PEPJ000101HDFLRNA1')->first();
        $this->assertNotNull($benef);
        $this->assertSame('12345', optional($benef->seccion)->seccional);
    }

    public function test_beneficiario_can_be_created_without_is_draft(): void
    {
        [$office] = $this->officeAndSeccion();
        $u = $this->capturistaForOffice($office);
        $payload = $this->validPayload();

        $response = $this->actingAs($u)->post(route('beneficiarios.store'), $payload);

        $response->assertRedirect(route('beneficiarios.create'));

        $this->assertDatabaseHas('beneficiarios', [
            'folio_tarjeta' => null,
            'nombre' => 'Juan',
            'apellido_paterno' => 'Perez',
        ]);

        $benef = \App\Models\Beneficiario::where('curp', 'PEPJ000101HDFLRNA1')->first();
        $this->assertNotNull($benef);
        $this->assertNull($benef->tarjeta_id);
        $this->assertSame('12345', optional($benef->seccion)->seccional);
        $this->assertNotNull($benef->municipio_id);

        $this->assertDatabaseHas('domicilios', [
            'beneficiario_id' => $benef->id,
            'calle' => 'Calle',
            'codigo_postal' => '01234',
        ]);
    }

    public function test_unknown_seccional_can_be_saved_when_municipio_is_selected(): void
    {
        [$office, $mun] = $this->officeAndSeccion();
        $u = $this->capturistaForOffice($office);
        $payload = $this->validPayload([
            'curp' => 'PEPJ000101HDFLRNB2',
            'id_ine' => '999990001122334455',
            'domicilio' => [
                'calle' => 'Calle',
                'numero_ext' => '1',
                'colonia' => 'Centro',
                'municipio_id' => $mun->id,
                'codigo_postal' => '01234',
                'seccional' => '99999',
            ],
        ]);

        $response = $this->actingAs($u)->post(route('beneficiarios.store'), $payload);

        $response->assertRedirect(route('beneficiarios.create'));
        $response->assertSessionHasNoErrors();

        $benef = \App\Models\Beneficiario::where('curp', 'PEPJ000101HDFLRNB2')->first();
        $this->assertNotNull($benef);
        $this->assertSame($mun->id, $benef->municipio_id);
        $this->assertNull($benef->seccion_id);

        $this->assertDatabaseHas('domicilios', [
            'beneficiario_id' => $benef->id,
            'municipio_id' => $mun->id,
            'seccion_id' => null,
        ]);
    }

    public function test_unknown_id_ine_ignores_a_stale_hidden_seccional_when_municipio_is_selected(): void
    {
        [$office, $mun] = $this->officeAndSeccion();
        $u = $this->capturistaForOffice($office);
        $otherMunicipio = Municipio::updateOrCreate(
            ['clave' => 2],
            ['nombre' => 'Otro municipio', 'oficina_id' => $office->id]
        );
        $otherSeccion = Seccion::updateOrCreate(
            ['seccional' => '54321'],
            ['municipio_id' => $otherMunicipio->id, 'distrito_local' => 'DL2', 'distrito_federal' => 'DF2']
        );

        $payload = $this->validPayload([
            'curp' => 'PEPJ000101HDFLRNC3',
            'id_ine' => '999990001122334455',
            'domicilio' => [
                'calle' => 'Calle',
                'numero_ext' => '1',
                'colonia' => 'Centro',
                'municipio_id' => $mun->id,
                'codigo_postal' => '01234',
                'seccional' => $otherSeccion->seccional,
            ],
        ]);

        $response = $this->actingAs($u)->post(route('beneficiarios.store'), $payload);

        $response->assertRedirect(route('beneficiarios.create'));
        $response->assertSessionHasNoErrors();

        $benef = \App\Models\Beneficiario::where('curp', 'PEPJ000101HDFLRNC3')->first();
        $this->assertNotNull($benef);
        $this->assertSame($mun->id, $benef->municipio_id);
        $this->assertNull($benef->seccion_id);
    }
}
