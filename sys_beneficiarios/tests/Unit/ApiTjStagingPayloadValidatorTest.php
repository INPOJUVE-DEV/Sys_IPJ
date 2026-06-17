<?php

namespace Tests\Unit;

use App\Models\Municipio;
use App\Models\Seccion;
use App\Services\Integrations\ApiTj\ApiTjStagingPayloadValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ApiTjStagingPayloadValidatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_validates_and_normalizes_a_valid_payload(): void
    {
        $municipio = Municipio::query()->create([
            'clave' => 1,
            'nombre' => 'Chihuahua',
        ]);

        Seccion::query()->create([
            'seccional' => '0123',
            'municipio_id' => $municipio->id,
            'distrito_local' => '01',
            'distrito_federal' => '01',
        ]);

        $validated = app(ApiTjStagingPayloadValidator::class)->validate([
            'external_request_id' => 'API-TJ-STG-0001',
            'source' => 'api_tj',
            'submitted_by' => [
                'system' => ' api_tj ',
                'user_id' => ' 123 ',
                'name' => ' Administrador API_TJ ',
            ],
            'beneficiario' => [
                'folio_tarjeta' => ' FOL-01 ',
                'nombre' => ' Ana ',
                'apellido_paterno' => ' Perez ',
                'apellido_materno' => ' Lopez ',
                'curp' => 'pelj000101hmnrrs09',
                'fecha_nacimiento' => '2000-01-01',
                'sexo' => 'f',
                'discapacidad' => true,
                'id_ine' => '012345678901234567',
                'telefono' => '6141234567',
                'domicilio' => [
                    'calle' => ' Principal ',
                    'numero_ext' => ' 100 ',
                    'numero_int' => ' ',
                    'colonia' => ' Centro ',
                    'municipio_id' => $municipio->id,
                    'codigo_postal' => ' 31000 ',
                    'seccional' => '0123',
                ],
            ],
        ]);

        $this->assertSame('api_tj', $validated['source']);
        $this->assertSame('api_tj', $validated['submitted_by']['system']);
        $this->assertSame('123', $validated['submitted_by']['user_id']);
        $this->assertSame('Administrador API_TJ', $validated['submitted_by']['name']);
        $this->assertSame('PELJ000101HMNRRS09', $validated['beneficiario']['curp']);
        $this->assertSame('Ana', $validated['beneficiario']['nombre']);
        $this->assertSame('F', $validated['beneficiario']['sexo']);
        $this->assertNull($validated['beneficiario']['domicilio']['numero_int']);
        $this->assertSame('31000', $validated['beneficiario']['domicilio']['codigo_postal']);
    }

    public function test_it_allows_missing_submitted_by(): void
    {
        $municipio = Municipio::query()->create([
            'clave' => 3,
            'nombre' => 'Delicias',
        ]);

        Seccion::query()->create([
            'seccional' => '0789',
            'municipio_id' => $municipio->id,
            'distrito_local' => '03',
            'distrito_federal' => '03',
        ]);

        $validated = app(ApiTjStagingPayloadValidator::class)->validate([
            'external_request_id' => 'API-TJ-STG-0003',
            'source' => 'api_tj',
            'beneficiario' => [
                'nombre' => 'Ana',
                'apellido_paterno' => 'Perez',
                'apellido_materno' => 'Lopez',
                'curp' => 'PELJ000101HMNRRS09',
                'fecha_nacimiento' => '2000-01-01',
                'sexo' => 'F',
                'discapacidad' => false,
                'id_ine' => '078912345678901234',
                'telefono' => '6141234567',
                'domicilio' => [
                    'calle' => 'Principal',
                    'numero_ext' => '100',
                    'colonia' => 'Centro',
                    'municipio_id' => $municipio->id,
                    'codigo_postal' => '33000',
                    'seccional' => '0789',
                ],
            ],
        ]);

        $this->assertArrayHasKey('submitted_by', $validated);
        $this->assertNull($validated['submitted_by']);
    }

    public function test_it_allows_optional_submitted_by_object_fields_to_be_empty(): void
    {
        $municipio = Municipio::query()->create([
            'clave' => 4,
            'nombre' => 'Parral',
        ]);

        Seccion::query()->create([
            'seccional' => '0999',
            'municipio_id' => $municipio->id,
            'distrito_local' => '04',
            'distrito_federal' => '04',
        ]);

        $validated = app(ApiTjStagingPayloadValidator::class)->validate([
            'external_request_id' => 'API-TJ-STG-0004',
            'source' => 'api_tj',
            'submitted_by' => [
                'system' => ' ',
                'user_id' => '',
            ],
            'beneficiario' => [
                'nombre' => 'Ana',
                'apellido_paterno' => 'Perez',
                'apellido_materno' => 'Lopez',
                'curp' => 'PELJ000101HMNRRS09',
                'fecha_nacimiento' => '2000-01-01',
                'sexo' => 'F',
                'discapacidad' => false,
                'id_ine' => '099912345678901234',
                'telefono' => '6141234567',
                'domicilio' => [
                    'calle' => 'Principal',
                    'numero_ext' => '100',
                    'colonia' => 'Centro',
                    'municipio_id' => $municipio->id,
                    'codigo_postal' => '33800',
                    'seccional' => '0999',
                ],
            ],
        ]);

        $this->assertSame([
            'system' => null,
            'user_id' => null,
            'name' => null,
        ], $validated['submitted_by']);
    }

    public function test_it_rejects_an_invalid_source(): void
    {
        $municipio = Municipio::query()->create([
            'clave' => 2,
            'nombre' => 'Juarez',
        ]);

        Seccion::query()->create([
            'seccional' => '0456',
            'municipio_id' => $municipio->id,
            'distrito_local' => '02',
            'distrito_federal' => '02',
        ]);

        $this->expectException(ValidationException::class);

        app(ApiTjStagingPayloadValidator::class)->validate([
            'external_request_id' => 'API-TJ-STG-0002',
            'source' => 'otro',
            'beneficiario' => [
                'nombre' => 'Ana',
                'apellido_paterno' => 'Perez',
                'apellido_materno' => 'Lopez',
                'curp' => 'PELJ000101HMNRRS09',
                'fecha_nacimiento' => '2000-01-01',
                'sexo' => 'F',
                'discapacidad' => false,
                'id_ine' => '045612345678901234',
                'telefono' => '6141234567',
                'domicilio' => [
                    'calle' => 'Principal',
                    'numero_ext' => '100',
                    'colonia' => 'Centro',
                    'municipio_id' => $municipio->id,
                    'codigo_postal' => '32000',
                    'seccional' => '0456',
                ],
            ],
        ]);
    }
}
