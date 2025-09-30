<?php

namespace Tests\Feature;

use App\Models\Beneficiario;
use App\Models\Municipio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KpisHttpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RoleSeeder::class);
    }

    public function test_admin_kpis_structure(): void
    {
        $admin = User::factory()->create(); $admin->assignRole('admin');
        $this->actingAs($admin)->get('/admin/kpis')
            ->assertOk()
            ->assertJsonStructure([
                'totals'=>['total'],
                'week' => ['labels','data','total'],
                'last30Days' => ['labels','data','total'],
            ]);
    }

    public function test_capturista_kpis_structure(): void
    {
        $mun = Municipio::create(['clave'=>1,'nombre'=>'Test']);
        $cap = User::factory()->create(); $cap->assignRole('capturista');
        Beneficiario::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'folio_tarjeta' => 'FT-HTTP',
            'nombre' => 'Juan', 'apellido_paterno'=>'P', 'apellido_materno'=>'L',
            'curp' => 'PEPJ000101HDFLRNA1',
            'fecha_nacimiento' => '2000-01-01', 'sexo'=>'M', 'discapacidad'=>false,
            'id_ine' => 'INE', 'telefono'=>'5512345678', 'municipio_id'=>$mun->id,
            'seccional'=>'001','distrito_local'=>'DL','distrito_federal'=>'DF','created_by'=> $cap->uuid,
        ]);
        $this->actingAs($cap)->get('/capturista/kpis')
            ->assertOk()
            ->assertJsonStructure(['today','week','last30Days','ultimos','series'=>['labels','data']]);
    }
}
