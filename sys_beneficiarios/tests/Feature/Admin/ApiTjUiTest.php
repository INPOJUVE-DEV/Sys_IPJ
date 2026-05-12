<?php

namespace Tests\Feature\Admin;

use App\Models\ApiTjInboundRequest;
use App\Models\ApiTjSyncRun;
use App\Models\Municipio;
use App\Models\Oficina;
use App\Models\Seccion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class ApiTjUiTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected Municipio $municipio;
    protected Seccion $seccion;
    private string $privateKeyPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RoleSeeder::class);
        $this->seed(\Database\Seeders\OficinaSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $delegacion = Oficina::where('tipo', Oficina::TIPO_DELEGACION)->firstOrFail();
        $this->municipio = Municipio::create([
            'clave' => 9801,
            'nombre' => 'Municipio UI QA',
            'region' => $delegacion->region,
            'oficina_id' => $delegacion->id,
        ]);

        $this->seccion = Seccion::create([
            'seccional' => '9801',
            'municipio_id' => $this->municipio->id,
            'distrito_local' => 'DL-UI',
            'distrito_federal' => 'DF-UI',
        ]);

        [, $privateKey] = $this->generateKeyPair();
        $this->privateKeyPath = storage_path('framework/testing/api-tj-ui-private.pem');
        if (! is_dir(dirname($this->privateKeyPath))) {
            mkdir(dirname($this->privateKeyPath), 0777, true);
        }
        file_put_contents($this->privateKeyPath, $privateKey);

        config([
            'api_tj.base_url' => 'http://localhost:8081',
            'api_tj.curp_hash_secret' => 'secret-test',
            'api_tj.outbound.private_key_path' => $this->privateKeyPath,
            'api_tj.outbound.jwt_kid' => 'sys_ipj-current',
            'api_tj.outbound.issuer' => 'sys_ipj',
            'api_tj.outbound.subject' => 'sys_ipj',
            'api_tj.outbound.audience' => 'api_tj',
            'api_tj.outbound.scope' => 'cardholders.sync',
            'api_tj.outbound.sync_path' => '/api/v1/cardholders/sync',
        ]);

        Http::fake([
            'http://host.docker.internal:8081/api/v1/cardholders/sync' => Http::response([
                'status' => 'success',
            ], 200),
        ]);
    }

    public function test_admin_api_tj_dashboard_renders_graphical_console(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.api-tj.index'))
            ->assertOk()
            ->assertSee('Centro de control API_TJ')
            ->assertSee('Consola QA inbound')
            ->assertSee('Historial de sync')
            ->assertSee('Ultimos syncs');
    }

    public function test_admin_api_tj_dashboard_handles_missing_audit_tables_gracefully(): void
    {
        Schema::dropIfExists('api_tj_inbound_requests');
        Schema::dropIfExists('api_tj_sync_runs');

        $this->actingAs($this->admin)
            ->get(route('admin.api-tj.index'))
            ->assertOk()
            ->assertSee('Configuracion pendiente')
            ->assertSee('api_tj_inbound_requests')
            ->assertSee('api_tj_sync_runs');
    }

    public function test_outbound_sync_entrypoints_are_visible_in_graphical_ui(): void
    {
        $delegacion = Oficina::where('tipo', Oficina::TIPO_DELEGACION)->firstOrFail();
        $delegado = User::factory()->create([
            'oficina_id' => $delegacion->id,
        ]);
        $delegado->assignRole('delegado');

        $this->actingAs($this->admin)
            ->get(route('admin.inventario.tarjetas.index'))
            ->assertOk()
            ->assertSee('Sincronizar con app')
            ->assertSee('Centro API_TJ');

        $this->actingAs($this->admin)
            ->get(route('beneficiarios.index'))
            ->assertOk()
            ->assertSee('Sincronizar con app')
            ->assertSee('API TJ');

        $this->actingAs($delegado)
            ->get(route('delegacion.inventario.tarjetas.index'))
            ->assertOk()
            ->assertSee('Sincronizar con app');
    }

    public function test_admin_can_execute_inbound_qa_from_browser_form(): void
    {
        $payload = [
            'external_request_id' => 'QA-UI-0001',
            'records' => [[
                'curp' => 'PEPJ800101HDFRRN09',
                'nombre' => 'JUAN',
                'apellido_paterno' => 'PEREZ',
                'apellido_materno' => 'LOPEZ',
                'fecha_nacimiento' => '1980-01-01',
                'telefono' => '5512345678',
                'folio_tarjeta' => 'TJ-UI-001',
                'domicilio' => [
                    'calle' => 'CALLE QA',
                    'numero_ext' => '11',
                    'numero_int' => '2',
                    'colonia' => 'CENTRO',
                    'municipio_id' => $this->municipio->id,
                    'codigo_postal' => '01000',
                    'seccional' => $this->seccion->seccional,
                ],
            ]],
        ];

        $response = $this->actingAs($this->admin)->post(route('admin.api-tj.qa.inbound'), [
            'payload_json' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);

        $requestRecord = ApiTjInboundRequest::where('external_request_id', 'QA-UI-0001')->first();

        $response->assertRedirect(route('admin.api-tj.requests.show', $requestRecord));
        $this->assertNotNull($requestRecord);
        $this->assertSame(ApiTjInboundRequest::STATUS_PROCESSED, $requestRecord->status);
    }

    public function test_admin_can_browse_sync_run_history_and_detail(): void
    {
        $run = ApiTjSyncRun::create([
            'id' => (string) Str::uuid(),
            'sync_id' => 'SYSIPJ-QA-0001',
            'executed_by' => $this->admin->uuid,
            'role' => 'admin',
            'started_at' => now(),
            'finished_at' => now(),
            'request_count' => 1,
            'request_payload_json' => ['sync_id' => 'SYSIPJ-QA-0001', 'items' => [['curp_hash' => 'hash']]],
            'success_count' => 1,
            'failed_count' => 0,
            'api_status_code' => 200,
            'api_response_body' => '{"status":"success"}',
            'status' => ApiTjSyncRun::STATUS_SUCCESS,
            'error_message' => null,
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.api-tj.sync-runs.index'))
            ->assertOk()
            ->assertSee('SYSIPJ-QA-0001');

        $this->actingAs($this->admin)
            ->get(route('admin.api-tj.sync-runs.show', $run))
            ->assertOk()
            ->assertSee('Detalle de sincronizacion API_TJ')
            ->assertSee('Payload enviado')
            ->assertSee('Respuesta API_TJ');
    }

    private function generateKeyPair(): array
    {
        $resource = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);

        openssl_pkey_export($resource, $privateKey);
        $details = openssl_pkey_get_details($resource);

        return [$details['key'], $privateKey];
    }
}
