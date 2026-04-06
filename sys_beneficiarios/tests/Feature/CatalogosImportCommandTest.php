<?php

namespace Tests\Feature;

use App\Models\Municipio;
use App\Models\Seccion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Tests\TestCase;

class CatalogosImportCommandTest extends TestCase
{
    use RefreshDatabase;

    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempDir = storage_path('framework/testing/catalogos-'.Str::uuid());
        if (! is_dir($this->tempDir)) {
            mkdir($this->tempDir, 0777, true);
        }
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempDir)) {
            foreach (glob($this->tempDir.DIRECTORY_SEPARATOR.'*') ?: [] as $file) {
                @unlink($file);
            }
            @rmdir($this->tempDir);
        }

        parent::tearDown();
    }

    public function test_import_command_syncs_and_prunes_catalogos_from_csv(): void
    {
        Municipio::create(['clave' => 999, 'nombre' => 'Stale']);
        $oldMunicipio = Municipio::create(['clave' => 1000, 'nombre' => 'To remove']);
        Seccion::create([
            'seccional' => '9999',
            'municipio_id' => $oldMunicipio->id,
            'distrito_local' => 'OLD',
            'distrito_federal' => 'OLD',
        ]);

        file_put_contents($this->tempDir.DIRECTORY_SEPARATOR.'municipios.csv', <<<CSV
id,clave,nombre
1,1,Ahualulco
2,2,Aquismon
CSV);

        file_put_contents($this->tempDir.DIRECTORY_SEPARATOR.'secciones.csv', <<<CSV
municipio_nombre,seccion,distrito_local,distrito_federal
Ahualulco,98,1,2
Aquismon,105,3,4
CSV);

        $code = Artisan::call('catalogos:import', [
            '--path' => $this->tempDir,
            '--prune' => true,
        ]);

        $this->assertSame(0, $code);

        $this->assertDatabaseHas('municipios', [
            'clave' => 1,
            'nombre' => 'Ahualulco',
        ]);
        $this->assertDatabaseHas('municipios', [
            'clave' => 2,
            'nombre' => 'Aquismon',
        ]);
        $this->assertDatabaseMissing('municipios', [
            'clave' => 999,
        ]);
        $this->assertDatabaseMissing('municipios', [
            'clave' => 1000,
        ]);

        $this->assertDatabaseHas('secciones', [
            'seccional' => '0098',
            'distrito_local' => '1',
            'distrito_federal' => '2',
        ]);
        $this->assertDatabaseHas('secciones', [
            'seccional' => '0105',
            'distrito_local' => '3',
            'distrito_federal' => '4',
        ]);
        $this->assertDatabaseMissing('secciones', [
            'seccional' => '9999',
        ]);
    }
}
