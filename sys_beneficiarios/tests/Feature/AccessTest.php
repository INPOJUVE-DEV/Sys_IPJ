<?php

namespace Tests\Feature;

use App\Models\Beneficiario;
use App\Models\Municipio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RoleSeeder::class);
    }

    public function test_login_required_for_protected_routes(): void
    {
        $this->get('/beneficiarios')->assertRedirect('/login');
    }

    public function test_capturista_cannot_access_admin(): void
    {
        $user = User::factory()->create();
        $user->assignRole('capturista');
        $this->actingAs($user)->get('/admin')->assertForbidden();
    }

    public function test_capturista_cannot_view_others_record(): void
    {
        $mun = Municipio::create(['clave'=>1,'nombre'=>'Test']);
        $a = User::factory()->create(); $a->assignRole('capturista');
        $b = User::factory()->create(); $b->assignRole('capturista');
        $benef = Beneficiario::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'folio_tarjeta' => 'F1',
            'nombre' => 'Uno', 'apellido_paterno'=>'A', 'apellido_materno'=>'B',
            'curp' => 'XEXX010101HNEXXXA4',
            'fecha_nacimiento' => '2000-01-01', 'sexo'=>'M', 'discapacidad'=>false,
            'id_ine' => 'INE', 'telefono'=>'5512345678', 'municipio_id'=>$mun->id,
            'seccional'=>'001','distrito_local'=>'DL','distrito_federal'=>'DF','created_by'=>$a->uuid,
        ]);
        $this->actingAs($b)->get(route('mis-registros.show', $benef))->assertForbidden();
    }
}

