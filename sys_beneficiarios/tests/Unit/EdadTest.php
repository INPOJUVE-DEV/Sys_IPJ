<?php

namespace Tests\Unit;

use App\Models\Beneficiario;
use App\Models\Municipio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EdadTest extends TestCase
{
    use RefreshDatabase;

    public function test_calcula_edad(): void
    {
        $mun = Municipio::create(['clave'=>1,'nombre'=>'Test']);
        $u = User::factory()->create();
        $b = Beneficiario::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'folio_tarjeta' => 'FT-EDAD',
            'nombre' => 'Juan', 'apellido_paterno'=>'P', 'apellido_materno'=>'L',
            'curp' => 'PEPJ000101HDFLRNA1',
            'fecha_nacimiento' => '2000-01-01', 'sexo'=>'M', 'discapacidad'=>false,
            'id_ine' => 'INE', 'telefono'=>'5512345678', 'municipio_id'=>$mun->id,
            'seccional'=>'001','distrito_local'=>'DL','distrito_federal'=>'DF','created_by'=> $u->uuid
        ]);
        $this->assertSame(\Carbon\Carbon::parse('2000-01-01')->age, $b->edad);
    }
}
