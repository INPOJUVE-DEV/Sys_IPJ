<?php

namespace Tests\Feature;

use App\Models\Beneficiario;
use App\Models\Municipio;
use App\Models\Tarjeta;
use App\Models\User;
use App\Services\Integrations\ApiTj\CardholderPayloadFactory;
use App\Services\Integrations\ApiTj\SkipSyncItemException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CardholderPayloadFactoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'integrations.outbound.hash_secret' => 'test-curp-secret',
        ]);
    }

    public function test_it_prefers_a_consumed_related_card(): void
    {
        $actor = User::factory()->create();
        $beneficiario = $this->makeBeneficiario($actor, 'PELA000101HSPABC01', 'LEG-001');
        $tarjeta = Tarjeta::query()->create([
            'id' => (string) Str::uuid(),
            'folio' => 'TJ-REAL-001',
            'estatus' => Tarjeta::STATUS_CONSUMIDA,
            'beneficiario_id' => $beneficiario->id,
        ]);

        $beneficiario->forceFill(['tarjeta_id' => $tarjeta->id])->save();

        $item = app(CardholderPayloadFactory::class)->makeItem($beneficiario->fresh('tarjeta'), now());

        $this->assertSame('TJ-REAL-001', $item['tarjeta_numero']);
        $this->assertSame('active', $item['status']);
        $this->assertSame('PELA************01', $item['curp_masked']);
    }

    public function test_it_includes_names_last_name_and_municipio_id_in_payload(): void
    {
        $actor = User::factory()->create();
        Municipio::query()->insert([
            'id' => 28,
            'clave' => 28,
            'nombre' => 'Test Municipio',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $beneficiario = $this->makeBeneficiario(
            $actor,
            'PELD000101HSPABC04',
            'LEG-004',
            [
                'nombre' => '  JUAN CARLOS  ',
                'apellido_paterno' => 'PEREZ',
                'apellido_materno' => 'LOPEZ',
                'municipio_id' => 28,
            ],
        );

        $item = app(CardholderPayloadFactory::class)->makeItem($beneficiario, now());

        $this->assertSame('JUAN CARLOS', $item['nombres']);
        $this->assertSame('PEREZ LOPEZ', $item['apellido']);
        $this->assertSame(28, $item['municipio_id']);
    }

    public function test_it_builds_last_name_without_double_spaces_when_maternal_last_name_is_missing(): void
    {
        $actor = User::factory()->create();
        $beneficiario = $this->makeBeneficiario(
            $actor,
            'PELE000101HSPABC05',
            'LEG-005',
            [
                'apellido_paterno' => 'PEREZ',
                'apellido_materno' => '   ',
            ],
        );

        $item = app(CardholderPayloadFactory::class)->makeItem($beneficiario, now());

        $this->assertSame('PEREZ', $item['apellido']);
    }

    public function test_it_falls_back_to_folio_tarjeta_when_no_consumed_card_exists(): void
    {
        $actor = User::factory()->create();
        $beneficiario = $this->makeBeneficiario($actor, 'PELB000101HSPABC02', 'LEG-002');

        $item = app(CardholderPayloadFactory::class)->makeItem($beneficiario, now());

        $this->assertSame('LEG-002', $item['tarjeta_numero']);
    }

    public function test_it_marks_beneficiario_without_valid_card_number_as_skippable(): void
    {
        $actor = User::factory()->create();
        $beneficiario = $this->makeBeneficiario($actor, 'PELC000101HSPABC03', null);

        $this->expectException(SkipSyncItemException::class);

        app(CardholderPayloadFactory::class)->makeItem($beneficiario, now());
    }

    private function makeBeneficiario(User $actor, string $curp, ?string $folioTarjeta, array $overrides = []): Beneficiario
    {
        return Beneficiario::query()->create(array_replace([
            'id' => (string) Str::uuid(),
            'folio_tarjeta' => $folioTarjeta,
            'nombre' => 'TEST',
            'apellido_paterno' => 'PERSONA',
            'apellido_materno' => 'SYNC',
            'curp' => $curp,
            'fecha_nacimiento' => '2000-01-01',
            'edad' => 24,
            'sexo' => 'M',
            'discapacidad' => false,
            'telefono' => '4441234567',
            'municipio_id' => null,
            'seccion_id' => null,
            'created_by' => $actor->uuid,
        ], $overrides));
    }
}
