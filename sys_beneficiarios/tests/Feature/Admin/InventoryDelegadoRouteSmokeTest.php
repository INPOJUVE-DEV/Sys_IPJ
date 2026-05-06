<?php

namespace Tests\Feature\Admin;

use App\Models\Beneficiario;
use App\Models\Municipio;
use App\Models\Oficina;
use App\Models\Programa;
use App\Models\Seccion;
use App\Models\Tarjeta;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class InventoryDelegadoRouteSmokeTest extends TestCase
{
    use RefreshDatabase;

    protected Oficina $central;
    protected Oficina $delegacion;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RoleSeeder::class);
        $this->seed(\Database\Seeders\OficinaSeeder::class);

        $this->central = Oficina::where('tipo', Oficina::TIPO_CENTRAL)->firstOrFail();
        $this->delegacion = Oficina::where('tipo', Oficina::TIPO_DELEGACION)->firstOrFail();

        $municipio = Municipio::create([
            'clave' => 9901,
            'nombre' => 'Municipio QA',
            'region' => $this->delegacion->region,
            'oficina_id' => $this->delegacion->id,
        ]);

        Seccion::create([
            'seccional' => '9901',
            'municipio_id' => $municipio->id,
            'distrito_local' => 'DL-QA',
            'distrito_federal' => 'DF-QA',
        ]);

        Programa::create([
            'nombre' => 'Programa QA',
            'slug' => 'programa-qa',
            'tipo_periodo' => 'mensual',
            'renovable' => true,
            'activo' => true,
        ]);
    }

    public function test_inventory_and_delegado_route_matrix_renders_or_blocks_by_role(): void
    {
        $admin = $this->userWithRole('admin');
        $delegado = $this->userWithRole('delegado', $this->delegacion);
        $capturista = $this->userWithRole('capturista', $this->delegacion);
        $capturistaProgramas = $this->userWithRole('capturista_programas', $this->delegacion);

        foreach ([
            route('admin.home'),
            route('stack.index'),
            route('admin.inventario.tarjetas.index'),
            route('admin.inventario.movimientos.index'),
            route('admin.usuarios.index'),
            route('admin.usuarios.create'),
        ] as $route) {
            $this->actingAs($admin)->get($route)->assertOk();
        }

        $this->actingAs($admin)->get(route('delegacion.home'))->assertForbidden();
        $this->actingAs($admin)->get(route('delegacion.inventario.tarjetas.index'))->assertForbidden();

        foreach ([
            route('delegacion.home'),
            route('stack.index'),
            route('delegacion.inventario.tarjetas.index'),
            route('delegacion.usuarios.index'),
            route('delegacion.usuarios.create'),
            route('beneficiarios.create'),
            route('inscripciones.index'),
        ] as $route) {
            $this->actingAs($delegado)->get($route)->assertOk();
        }

        $this->actingAs($delegado)->get(route('admin.home'))->assertForbidden();
        $this->actingAs($delegado)->get(route('admin.inventario.tarjetas.index'))->assertForbidden();
        $this->actingAs($delegado)->get(route('admin.usuarios.index'))->assertForbidden();
        $this->actingAs($delegado)->get(route('programas.index'))->assertForbidden();

        $this->actingAs($capturista)->get(route('beneficiarios.create'))->assertOk();
        $this->actingAs($capturista)->get(route('mis-registros.index'))->assertOk();
        $this->actingAs($capturista)->get(route('stack.index'))->assertForbidden();
        $this->actingAs($capturista)->get(route('delegacion.home'))->assertForbidden();
        $this->actingAs($capturista)->get(route('admin.home'))->assertForbidden();

        $this->actingAs($capturistaProgramas)->get(route('inscripciones.index'))->assertOk();
        $this->actingAs($capturistaProgramas)->get(route('beneficiarios.create'))->assertForbidden();
        $this->actingAs($capturistaProgramas)->get(route('stack.index'))->assertForbidden();
    }

    public function test_removed_vales_inventory_routes_return_404(): void
    {
        $admin = $this->userWithRole('admin');
        $delegado = $this->userWithRole('delegado', $this->delegacion);

        $this->actingAs($admin)
            ->get('/admin/inventario/vales')
            ->assertNotFound();

        $this->actingAs($delegado)
            ->get('/delegacion/inventario/vales')
            ->assertNotFound();
    }

    public function test_delegacion_dashboard_shows_captured_cards_age_target_board(): void
    {
        $delegado = $this->userWithRole('delegado', $this->delegacion);
        $municipio = Municipio::where('clave', 9901)->firstOrFail();
        $seccion = Seccion::where('municipio_id', $municipio->id)->firstOrFail();

        $beneficiario = Beneficiario::create([
            'id' => (string) Str::uuid(),
            'folio_tarjeta' => 'CAP-001',
            'nombre' => 'Persona',
            'apellido_paterno' => 'Objetivo',
            'apellido_materno' => 'Regional',
            'curp' => 'PORE000101HSPRGNA1',
            'fecha_nacimiento' => '2000-01-01',
            'edad' => 26,
            'sexo' => 'M',
            'discapacidad' => false,
            'id_ine' => 'INE-CAP-001',
            'telefono' => '5511111111',
            'municipio_id' => $municipio->id,
            'seccion_id' => $seccion->id,
            'created_by' => $delegado->uuid,
        ]);

        $tarjeta = Tarjeta::create([
            'id' => (string) Str::uuid(),
            'folio' => 'CAP-001',
            'estatus' => Tarjeta::STATUS_CONSUMIDA,
            'oficina_id' => $this->delegacion->id,
            'municipio_id' => $municipio->id,
            'usuario_uuid' => $delegado->uuid,
            'beneficiario_id' => $beneficiario->id,
        ]);

        $beneficiario->forceFill(['tarjeta_id' => $tarjeta->id])->save();

        Tarjeta::create([
            'id' => (string) Str::uuid(),
            'folio' => 'ASIG-001',
            'estatus' => Tarjeta::STATUS_ASIGNADA_OFICINA,
            'oficina_id' => $this->delegacion->id,
            'municipio_id' => $municipio->id,
        ]);

        $this->actingAs($delegado)
            ->get(route('delegacion.home'))
            ->assertOk()
            ->assertSee('Beneficiarios capturados de sus municipios')
            ->assertSee('Tablero de edad objetivo')
            ->assertSee('Municipio QA')
            ->assertSeeInOrder(['Municipio QA', '2', '1', '1', '0']);
    }

    protected function userWithRole(string $role, ?Oficina $oficina = null): User
    {
        $user = User::factory()->create([
            'oficina_id' => $oficina?->id,
        ]);
        $user->assignRole($role);

        return $user;
    }
}
