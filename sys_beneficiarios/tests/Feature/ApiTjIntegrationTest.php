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
use Laravel\Sanctum\Sanctum;
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
            'api_tj.base_url' => 'https://apitj-production.up.railway.app',
            'api_tj.curp_hash_secret' => 'secret-test',
            'api_tj.inbound.public_key' => $publicKey,
            'api_tj.inbound.jwt_kid' => 'api_tj-current',
            'api_tj.inbound.audience' => 'sys_ipj',
            'api_tj.inbound.allowed_scope' => 'beneficiarios.create',
            'api_tj.outbound.private_key_path' => $this->privateKeyPath,
            'api_tj.outbound.jwt_kid' => 'sys_ipj-current',
            'api_tj.outbound.issuer' => 'sys_ipj',
            'api_tj.outbound.subject' => 'sys_ipj',
            'api_tj.outbound.audience' => 'api_tj',
            'api_tj.outbound.scope' => 'cardholders.sync',
            'api_tj.outbound.sync_path' => '/api/v1/cardholders/sync',
        ]);
    }

    public function test_inbound_batch_creates_beneficiario_and_audit_request(): void
    {
        [$municipio, $seccion] = $this->municipioAndSeccion();
        $payload = $this->validBatchPayload($municipio->id, $seccion->seccional);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->makeInboundToken())
            ->postJson('/api/api-tj/inbound', $payload);

        $response->assertCreated()
            ->assertJsonPath('external_request_id', 'INF-20260506-0001')
            ->assertJsonPath('total', 1)
            ->assertJsonPath('accepted_count', 1)
            ->assertJsonPath('rejected_count', 0)
            ->assertJsonPath('results.0.status', 'created');

        $beneficiarioId = $response->json('results.0.beneficiario_id');
        $this->assertDatabaseHas('beneficiarios', [
            'id' => $beneficiarioId,
            'curp' => 'PEPJ800101HDFRRN09',
            'email' => 'juan@example.com',
            'api_tj_sync_status' => Beneficiario::API_TJ_SYNC_STATUS_PENDING_SYNC,
        ]);
        $this->assertDatabaseHas('api_tj_inbound_requests', [
            'external_request_id' => 'INF-20260506-0001',
            'status' => ApiTjInboundRequest::STATUS_PROCESSED,
            'total_count' => 1,
            'accepted_count' => 1,
            'rejected_count' => 0,
        ]);
    }

    public function test_inbound_rejects_expired_jwt(): void
    {
        [$municipio, $seccion] = $this->municipioAndSeccion();

        $response = $this->withHeader('Authorization', 'Bearer '.$this->makeInboundToken(-1))
            ->postJson('/api/api-tj/inbound', $this->validBatchPayload($municipio->id, $seccion->seccional));

        $response->assertUnauthorized()
            ->assertJsonPath('status', 'unauthorized');
    }

    public function test_inbound_updates_existing_beneficiario_when_curp_matches(): void
    {
        [$municipio, $seccion] = $this->municipioAndSeccion();
        $existing = Beneficiario::create([
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
            'id_ine' => null,
            'telefono' => '5511111111',
            'email' => null,
            'municipio_id' => $municipio->id,
            'seccion_id' => $seccion->id,
            'created_by' => null,
        ]);

        $payload = $this->validBatchPayload($municipio->id, $seccion->seccional, [
            'external_request_id' => 'INF-20260506-0002',
            'records' => [[
                'curp' => 'PEPJ800101HDFRRN09',
                'nombre' => 'JUAN',
                'apellido_paterno' => 'PEREZ',
                'apellido_materno' => 'LOPEZ',
                'fecha_nacimiento' => '1980-01-01',
                'telefono' => '5512345678',
                'email' => 'juan.actualizado@example.com',
                'folio_tarjeta' => 'TJ-000124',
                'domicilio' => [
                    'calle' => 'CALLE 2',
                    'numero_ext' => '20',
                    'numero_int' => null,
                    'colonia' => 'CENTRO',
                    'municipio_id' => $municipio->id,
                    'codigo_postal' => '01000',
                    'seccional' => $seccion->seccional,
                ],
            ]],
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->makeInboundToken())
            ->postJson('/api/api-tj/inbound', $payload);

        $response->assertOk()
            ->assertJsonPath('results.0.status', 'updated')
            ->assertJsonPath('accepted_count', 1);

        $this->assertSame(1, Beneficiario::count());
        $this->assertDatabaseHas('beneficiarios', [
            'id' => $existing->id,
            'nombre' => 'JUAN',
            'telefono' => '5512345678',
            'email' => 'juan.actualizado@example.com',
            'folio_tarjeta' => 'TJ-000124',
            'api_tj_sync_status' => Beneficiario::API_TJ_SYNC_STATUS_PENDING_SYNC,
        ]);
    }

    public function test_inbound_without_email_keeps_beneficiario_eligible_for_minimum_sync(): void
    {
        [$municipio, $seccion] = $this->municipioAndSeccion();
        $payload = $this->validBatchPayload($municipio->id, $seccion->seccional, [
            'records' => [[
                'folio_tarjeta' => 'TJ-000123',
            ]],
        ]);
        unset($payload['records'][0]['email']);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->makeInboundToken())
            ->postJson('/api/api-tj/inbound', $payload);

        $response->assertCreated()
            ->assertJsonPath('results.0.status', 'created');

        $beneficiarioId = $response->json('results.0.beneficiario_id');
        $this->assertDatabaseHas('beneficiarios', [
            'id' => $beneficiarioId,
            'api_tj_sync_status' => Beneficiario::API_TJ_SYNC_STATUS_PENDING_SYNC,
            'folio_tarjeta' => 'TJ-000123',
            'email' => null,
        ]);
    }

    public function test_sync_endpoint_exports_only_pending_sync_beneficiarios_and_marks_them_synced(): void
    {
        Http::fake([
            'https://apitj-production.up.railway.app/api/v1/cardholders/sync' => Http::response([
                'status' => 'success',
            ], 200),
        ]);

        $admin = User::factory()->create();
        $admin->assignRole('admin');
        [$municipio, $seccion] = $this->municipioAndSeccion();

        $eligible = Beneficiario::create([
            'id' => (string) Str::uuid(),
            'folio_tarjeta' => 'TJ-000456',
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
            'email' => 'eligible@example.com',
            'municipio_id' => $municipio->id,
            'seccion_id' => $seccion->id,
            'created_by' => $admin->uuid,
        ]);

        Beneficiario::create([
            'id' => (string) Str::uuid(),
            'folio_tarjeta' => null,
            'tarjeta_id' => null,
            'nombre' => 'Sin',
            'apellido_paterno' => 'Correo',
            'apellido_materno' => 'Pendiente',
            'curp' => 'PELJ800101HDFRRN08',
            'fecha_nacimiento' => '1980-01-01',
            'edad' => 44,
            'sexo' => 'M',
            'discapacidad' => false,
            'id_ine' => 'INE-PD',
            'telefono' => '5511111112',
            'email' => null,
            'municipio_id' => $municipio->id,
            'seccion_id' => $seccion->id,
            'created_by' => $admin->uuid,
        ]);

        Sanctum::actingAs($admin);
        $response = $this->postJson('/api/api-tj/sync');

        $response->assertOk()
            ->assertJsonPath('status', ApiTjSyncRun::STATUS_SUCCESS)
            ->assertJsonPath('request_count', 1)
            ->assertJsonPath('success_count', 1)
            ->assertJsonPath('failed_count', 0);

        Http::assertSent(function ($request) use ($eligible) {
            $items = $request['items'] ?? [];

            return $request->url() === 'https://apitj-production.up.railway.app/api/v1/cardholders/sync'
                && count($items) === 1
                && $items[0]['curp_hash'] === $eligible->fresh()->curp_hash
                && $items[0]['curp_masked'] === 'PEPJ**********09'
                && $items[0]['tarjeta_numero'] === 'TJ-000456'
                && $items[0]['status'] === 'active'
                && ! isset($items[0]['email']);
        });

        $this->assertDatabaseHas('api_tj_sync_runs', [
            'status' => ApiTjSyncRun::STATUS_SUCCESS,
            'request_count' => 1,
            'success_count' => 1,
            'failed_count' => 0,
        ]);
        $this->assertDatabaseHas('beneficiarios', [
            'id' => $eligible->id,
            'api_tj_sync_status' => Beneficiario::API_TJ_SYNC_STATUS_SYNCED,
        ]);
    }

    public function test_sync_failure_marks_beneficiario_for_retry(): void
    {
        Http::fake([
            'https://apitj-production.up.railway.app/api/v1/cardholders/sync' => Http::response([
                'message' => 'upstream down',
            ], 500),
        ]);

        $admin = User::factory()->create();
        $admin->assignRole('admin');
        [$municipio, $seccion] = $this->municipioAndSeccion();

        $beneficiario = Beneficiario::create([
            'id' => (string) Str::uuid(),
            'folio_tarjeta' => 'TJ-000789',
            'tarjeta_id' => null,
            'nombre' => 'Error',
            'apellido_paterno' => 'Sync',
            'apellido_materno' => 'Caso',
            'curp' => 'PEPJ800101HDFRRN09',
            'fecha_nacimiento' => '1980-01-01',
            'edad' => 44,
            'sexo' => 'M',
            'discapacidad' => false,
            'id_ine' => 'INE-ERR',
            'telefono' => '5511111111',
            'email' => 'error@example.com',
            'municipio_id' => $municipio->id,
            'seccion_id' => $seccion->id,
            'created_by' => $admin->uuid,
        ]);

        Sanctum::actingAs($admin);
        $response = $this->postJson('/api/api-tj/sync');

        $response->assertStatus(502)
            ->assertJsonPath('status', ApiTjSyncRun::STATUS_FAILED)
            ->assertJsonPath('failed_count', 1);

        $this->assertDatabaseHas('api_tj_sync_runs', [
            'status' => ApiTjSyncRun::STATUS_FAILED,
            'request_count' => 1,
            'success_count' => 0,
            'failed_count' => 1,
        ]);
        $this->assertDatabaseHas('beneficiarios', [
            'id' => $beneficiario->id,
            'api_tj_sync_status' => Beneficiario::API_TJ_SYNC_STATUS_SYNC_FAILED,
            'api_tj_sync_attempts' => 1,
        ]);
    }

    private function municipioAndSeccion(): array
    {
        $office = Oficina::where('tipo', Oficina::TIPO_DELEGACION)->orderBy('id')->firstOrFail();
        $municipio = Municipio::create([
            'clave' => random_int(900, 999),
            'nombre' => 'Municipio API TJ '.Str::random(4),
            'region' => $office->region,
            'oficina_id' => $office->id,
        ]);
        $seccion = Seccion::create([
            'seccional' => Str::padLeft((string) random_int(1, 9999), 4, '0'),
            'municipio_id' => $municipio->id,
            'distrito_local' => '01',
            'distrito_federal' => '01',
        ]);

        return [$municipio, $seccion];
    }

    private function validBatchPayload(int $municipioId, string $seccional, array $overrides = []): array
    {
        $base = [
            'external_request_id' => 'INF-20260506-0001',
            'records' => [[
                'curp' => 'PEPJ800101HDFRRN09',
                'nombre' => 'JUAN',
                'apellido_paterno' => 'PEREZ',
                'apellido_materno' => 'LOPEZ',
                'fecha_nacimiento' => '1980-01-01',
                'telefono' => '5512345678',
                'email' => 'juan@example.com',
                'folio_tarjeta' => 'TJ-000123',
                'domicilio' => [
                    'calle' => 'CALLE 1',
                    'numero_ext' => '10',
                    'numero_int' => '2',
                    'colonia' => 'CENTRO',
                    'municipio_id' => $municipioId,
                    'codigo_postal' => '01000',
                    'seccional' => $seccional,
                ],
            ]],
        ];

        return array_replace_recursive($base, $overrides);
    }

    private function makeInboundToken(int $expiresInMinutes = 10): string
    {
        $issuedAt = now();

        return app(ApiTjJwtService::class)->sign([
            'iss' => 'api_tj',
            'sub' => 'api_tj',
            'aud' => 'sys_ipj',
            'scope' => 'beneficiarios.create',
            'jti' => (string) Str::uuid(),
            'iat' => $issuedAt->timestamp,
            'exp' => $issuedAt->copy()->addMinutes($expiresInMinutes)->timestamp,
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
