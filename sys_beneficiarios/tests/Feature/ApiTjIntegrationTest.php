<?php

namespace Tests\Feature;

use App\Models\ApiTjInboundRequest;
use App\Models\ApiTjSyncRun;
use App\Models\Beneficiario;
use App\Models\Municipio;
use App\Models\Oficina;
use App\Models\Seccion;
use App\Models\User;
use App\Services\ApiTjJwtService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class ApiTjIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private string $privateKeyPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $this->seed(\Database\Seeders\OficinaSeeder::class);

        [$publicKey, $privateKey] = $this->generateKeyPair();
        $this->privateKeyPath = storage_path('framework/testing/api-tj-private.pem');
        if (! is_dir(dirname($this->privateKeyPath))) {
            mkdir(dirname($this->privateKeyPath), 0777, true);
        }
        file_put_contents($this->privateKeyPath, $privateKey);

        config([
            'services.api_tj.public_key' => $publicKey,
            'services.api_tj.jwt_kid' => 'api_tj-current',
            'services.api_tj.audience' => 'sys_ipj',
            'services.api_tj.allowed_scope' => 'beneficiarios.create',
            'services.api_tj.base_url' => 'https://apitj-production.up.railway.app',
            'services.sys_ipj.private_key_path' => $this->privateKeyPath,
            'services.sys_ipj.jwt_kid' => 'sys_ipj-current',
            'services.sys_ipj.client_code' => 'sys_ipj',
            'services.sys_ipj.audience' => 'api_tj',
            'services.sys_ipj.scope' => 'cardholders.sync',
            'services.sys_ipj.curp_hash_secret' => 'secret-test',
        ]);
    }

    public function test_inbound_request_creates_beneficiario_and_domicilio(): void
    {
        [$municipio, $seccion] = $this->municipioAndSeccion();
        $payload = $this->validPayload($municipio->id, $seccion->seccional);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->makeInboundToken())
            ->postJson('/api/v1/integrations/api-tj/beneficiarios', $payload);

        $response->assertCreated()
            ->assertJsonPath('accepted', true)
            ->assertJsonPath('status', 'created');

        $beneficiarioId = $response->json('beneficiario_id');
        $this->assertDatabaseHas('beneficiarios', [
            'id' => $beneficiarioId,
            'curp' => 'PEPJ800101HDFRRN09',
            'created_by' => null,
            'municipio_id' => $municipio->id,
            'seccion_id' => $seccion->id,
        ]);
        $this->assertDatabaseHas('domicilios', [
            'beneficiario_id' => $beneficiarioId,
            'municipio_id' => $municipio->id,
            'seccion_id' => $seccion->id,
        ]);
        $this->assertDatabaseHas('api_tj_inbound_requests', [
            'external_request_id' => 'INF-20260426-0001',
            'status' => ApiTjInboundRequest::STATUS_CREATED,
            'beneficiario_id' => $beneficiarioId,
        ]);
    }

    public function test_inbound_request_is_idempotent_by_external_request_id(): void
    {
        [$municipio, $seccion] = $this->municipioAndSeccion();
        $payload = $this->validPayload($municipio->id, $seccion->seccional);
        $headers = ['Authorization' => 'Bearer '.$this->makeInboundToken()];

        $this->withHeaders($headers)->postJson('/api/v1/integrations/api-tj/beneficiarios', $payload)->assertCreated();
        $second = $this->withHeaders($headers)->postJson('/api/v1/integrations/api-tj/beneficiarios', $payload);

        $second->assertOk()
            ->assertJsonPath('status', 'already_processed');

        $this->assertSame(1, Beneficiario::count());
    }

    public function test_inbound_request_conflicts_on_existing_curp_with_new_external_request_id(): void
    {
        [$municipio, $seccion] = $this->municipioAndSeccion();
        Beneficiario::create([
            'id' => (string) Str::uuid(),
            'folio_tarjeta' => null,
            'tarjeta_id' => null,
            'nombre' => 'Previo',
            'apellido_paterno' => 'Existente',
            'apellido_materno' => 'Prueba',
            'curp' => 'PEPJ800101HDFRRN09',
            'fecha_nacimiento' => '1980-01-01',
            'edad' => 44,
            'sexo' => 'M',
            'discapacidad' => false,
            'id_ine' => 'INE-PREVIO',
            'telefono' => '5511111111',
            'municipio_id' => $municipio->id,
            'seccion_id' => $seccion->id,
            'created_by' => null,
        ]);

        $payload = $this->validPayload($municipio->id, $seccion->seccional);
        $payload['external_request_id'] = 'INF-20260426-0002';

        $response = $this->withHeader('Authorization', 'Bearer '.$this->makeInboundToken())
            ->postJson('/api/v1/integrations/api-tj/beneficiarios', $payload);

        $response->assertStatus(409)
            ->assertJsonPath('status', 'conflict');
    }

    public function test_inbound_request_rejects_missing_external_request_id(): void
    {
        [$municipio, $seccion] = $this->municipioAndSeccion();
        $payload = $this->validPayload($municipio->id, $seccion->seccional);
        unset($payload['external_request_id']);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->makeInboundToken())
            ->postJson('/api/v1/integrations/api-tj/beneficiarios', $payload);

        $response->assertStatus(422)
            ->assertJsonPath('status', 'validation_error')
            ->assertJsonStructure(['errors' => ['external_request_id']]);
    }

    public function test_inbound_request_rejects_invalid_jwt(): void
    {
        [$municipio, $seccion] = $this->municipioAndSeccion();

        $response = $this->withHeader('Authorization', 'Bearer invalid.token.value')
            ->postJson('/api/v1/integrations/api-tj/beneficiarios', $this->validPayload($municipio->id, $seccion->seccional));

        $response->assertUnauthorized()
            ->assertJsonPath('status', 'unauthorized');
    }

    public function test_sync_sends_td_folio_and_creates_audit_run(): void
    {
        Http::fake([
            'https://apitj-production.up.railway.app/api/v1/cardholders/sync' => Http::response([
                'status' => 'success',
                'success_count' => 1,
                'failed_count' => 0,
            ], 200),
        ]);

        $admin = User::factory()->create();
        $admin->assignRole('admin');
        [$municipio, $seccion] = $this->municipioAndSeccion();

        Beneficiario::create([
            'id' => (string) Str::uuid(),
            'folio_tarjeta' => 'TD-00001',
            'tarjeta_id' => null,
            'nombre' => 'Digital',
            'apellido_paterno' => 'Prueba',
            'apellido_materno' => 'Uno',
            'curp' => 'PEPJ800101HDFRRN09',
            'fecha_nacimiento' => '1980-01-01',
            'edad' => 44,
            'sexo' => 'M',
            'discapacidad' => false,
            'id_ine' => 'INE-TD',
            'telefono' => '5511111111',
            'municipio_id' => $municipio->id,
            'seccion_id' => $seccion->id,
            'created_by' => $admin->uuid,
        ]);

        $response = $this->actingAs($admin)->post(route('admin.api-tj.sync'));

        $response->assertRedirect();
        Http::assertSent(function ($request) {
            $items = $request['items'] ?? [];

            return $request->hasHeader('Authorization')
                && count($items) === 1
                && $items[0]['tarjeta_numero'] === 'TD-00001'
                && $items[0]['status'] === 'active'
                && ! isset($items[0]['curp']);
        });

        $this->assertDatabaseHas('api_tj_sync_runs', [
            'status' => ApiTjSyncRun::STATUS_SUCCESS,
            'request_count' => 1,
            'success_count' => 1,
            'failed_count' => 0,
        ]);
    }

    private function municipioAndSeccion(): array
    {
        $office = Oficina::where('tipo', Oficina::TIPO_DELEGACION)->orderBy('id')->firstOrFail();
        $municipio = Municipio::create([
            'clave' => 901,
            'nombre' => 'Municipio API TJ',
            'region' => $office->region,
            'oficina_id' => $office->id,
        ]);
        $seccion = Seccion::create([
            'seccional' => '0001',
            'municipio_id' => $municipio->id,
            'distrito_local' => '01',
            'distrito_federal' => '01',
        ]);

        return [$municipio, $seccion];
    }

    private function validPayload(int $municipioId, string $seccional): array
    {
        return [
            'external_request_id' => 'INF-20260426-0001',
            'beneficiario' => [
                'curp' => 'PEPJ800101HDFRRN09',
                'nombre' => 'JUAN',
                'apellido_paterno' => 'PEREZ',
                'apellido_materno' => 'LOPEZ',
                'fecha_nacimiento' => '1980-01-01',
                'sexo' => 'M',
                'discapacidad' => false,
                'id_ine' => 'ABC123',
                'telefono' => '5512345678',
                'domicilio' => [
                    'calle' => 'CALLE 1',
                    'numero_ext' => '10',
                    'numero_int' => '2',
                    'colonia' => 'CENTRO',
                    'municipio_id' => $municipioId,
                    'codigo_postal' => '01000',
                    'seccional' => $seccional,
                ],
            ],
        ];
    }

    private function makeInboundToken(): string
    {
        return app(ApiTjJwtService::class)->sign([
            'iss' => 'api_tj',
            'sub' => 'api_tj',
            'aud' => 'sys_ipj',
            'scope' => 'beneficiarios.create',
            'jti' => (string) Str::uuid(),
            'iat' => now()->timestamp,
            'exp' => now()->addMinutes(10)->timestamp,
        ], [
            'alg' => 'RS256',
            'typ' => 'JWT',
            'kid' => 'api_tj-current',
        ], $this->privateKeyPath);
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
