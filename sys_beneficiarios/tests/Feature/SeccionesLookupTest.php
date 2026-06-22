<?php

namespace Tests\Feature;

use App\Models\Municipio;
use App\Models\Oficina;
use App\Models\Seccion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeccionesLookupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\OficinaSeeder::class);
    }

    public function test_lookup_returns_found_true_for_existing_seccional(): void
    {
        $office = Oficina::where('tipo', Oficina::TIPO_DELEGACION)->orderBy('id')->firstOrFail();
        $municipio = Municipio::updateOrCreate(
            ['clave' => 1],
            ['nombre' => 'Test', 'oficina_id' => $office->id]
        );
        Seccion::updateOrCreate(
            ['seccional' => '12345'],
            ['municipio_id' => $municipio->id, 'distrito_local' => 'DL', 'distrito_federal' => 'DF']
        );

        $this->getJson('/api/v1/secciones/12345')
            ->assertOk()
            ->assertJsonPath('found', true)
            ->assertJsonPath('seccional', '12345')
            ->assertJsonPath('municipio_id', $municipio->id)
            ->assertJsonPath('municipio', 'Test');
    }

    public function test_lookup_returns_found_false_instead_of_404_for_unknown_seccional(): void
    {
        $this->getJson('/api/v1/secciones/99999')
            ->assertOk()
            ->assertJsonPath('found', false)
            ->assertJsonPath('seccional', '99999')
            ->assertJsonPath('municipio_id', null);
    }
}
