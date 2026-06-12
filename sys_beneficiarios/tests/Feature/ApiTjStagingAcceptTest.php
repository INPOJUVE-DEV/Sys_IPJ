<?php

namespace Tests\Feature;

use App\Models\Beneficiario;
use App\Models\Integrations\IntegrationClient;
use App\Models\Integrations\IntegrationClientKey;
use App\Models\Integrations\IntegrationInboundRequest;
use App\Models\Municipio;
use App\Models\Seccion;
use App\Models\User;
use App\Services\Integrations\Security\Contracts\JwtCodec;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\Integrations\TestJwtCodec;
use Tests\TestCase;

class ApiTjStagingAcceptTest extends TestCase
{
    use RefreshDatabase;

    private Municipio $municipio;

    private Seccion $seccion;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'integrations.inbound.audience' => 'sys_ipj',
            'integrations.inbound.max_token_ttl_seconds' => 600,
            'integrations.payload_encryption_key' => 'test-inbound-payload-key',
            'integrations.api_tj.integration_user_email' => 'integracion.api_tj@inpojuve.local',
        ]);

        $this->app->instance(JwtCodec::class, new TestJwtCodec());

        $this->municipio = Municipio::query()->create([
            'clave' => 8,
            'nombre' => 'Chihuahua',
        ]);

        $this->seccion = Seccion::query()->create([
            'seccional' => '0123',
            'municipio_id' => $this->municipio->id,
            'distrito_local' => '01',
            'distrito_federal' => '01',
        ]);

        $this->seedClient();
    }

    public function test_request_without_token_is_rejected(): void
    {
        $this->postJson('/api/v1/integrations/api-tj/staging/accept', $this->makePayload())
            ->assertUnauthorized()
            ->assertJson([
                'accepted' => false,
                'status' => 'unauthorized',
                'message' => 'Token de integracion invalido',
            ]);
    }

    public function test_invalid_payload_returns_validation_error_contract(): void
    {
        $response = $this->authorizedPost([
            'beneficiario' => [
                'domicilio' => [
                    'municipio_id' => 999999,
                ],
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('accepted', false)
            ->assertJsonPath('status', 'validation_error')
            ->assertJsonPath('external_request_id', 'API-TJ-STG-2001');

        $this->assertDatabaseHas('integration_inbound_requests', [
            'external_request_id' => 'API-TJ-STG-2001',
            'status' => IntegrationInboundRequest::STATUS_REJECTED,
        ]);
    }

    public function test_same_external_request_id_with_different_payload_returns_conflict_without_overwriting_request(): void
    {
        User::factory()->create([
            'email' => 'integracion.api_tj@inpojuve.local',
            'name' => 'Integracion API_TJ',
        ]);

        $firstResponse = $this->authorizedPost();
        $firstResponse->assertCreated();

        $request = IntegrationInboundRequest::query()
            ->where('external_request_id', 'API-TJ-STG-2001')
            ->firstOrFail();

        $originalHash = $request->request_hash;
        $originalPayload = $request->request_payload_encrypted;

        $secondResponse = $this->authorizedPost([
            'beneficiario' => [
                'nombre' => 'OTRA',
            ],
        ], (string) Str::uuid());

        $secondResponse->assertStatus(409)
            ->assertJsonPath('accepted', false)
            ->assertJsonPath('status', 'conflict');

        $request->refresh();

        $this->assertSame($originalHash, $request->request_hash);
        $this->assertSame($originalPayload, $request->request_payload_encrypted);
        $this->assertSame(IntegrationInboundRequest::STATUS_ACCEPTED, $request->status);
    }

    public function test_duplicate_curp_returns_conflict_without_creating_beneficiary(): void
    {
        $technicalUser = User::factory()->create([
            'email' => 'integracion.api_tj@inpojuve.local',
        ]);

        Beneficiario::query()->create([
            'id' => (string) Str::uuid(),
            'folio_tarjeta' => 'FOL-DUP-01',
            'nombre' => 'ANA',
            'apellido_paterno' => 'PEREZ',
            'apellido_materno' => 'LOPEZ',
            'curp' => 'PELJ000101HMNRRS09',
            'fecha_nacimiento' => '2000-01-01',
            'edad' => 24,
            'sexo' => 'F',
            'discapacidad' => false,
            'id_ine' => '012345678901234567',
            'telefono' => '6141234567',
            'municipio_id' => $this->municipio->id,
            'seccion_id' => $this->seccion->id,
            'created_by' => $technicalUser->uuid,
        ]);

        $response = $this->authorizedPost();

        $response->assertStatus(409)
            ->assertJsonPath('accepted', false)
            ->assertJsonPath('status', 'duplicate');

        $this->assertDatabaseCount('beneficiarios', 1);
        $this->assertDatabaseHas('integration_inbound_requests', [
            'external_request_id' => 'API-TJ-STG-2001',
            'status' => IntegrationInboundRequest::STATUS_REJECTED,
        ]);
    }

    public function test_missing_technical_user_returns_controlled_server_error(): void
    {
        $response = $this->authorizedPost();

        $response->assertStatus(500)
            ->assertJsonPath('accepted', false)
            ->assertJsonPath('status', 'error');

        $this->assertDatabaseHas('integration_inbound_requests', [
            'external_request_id' => 'API-TJ-STG-2001',
            'status' => IntegrationInboundRequest::STATUS_FAILED,
        ]);
    }

    public function test_valid_request_creates_beneficiary_and_audits_request(): void
    {
        User::factory()->create([
            'email' => 'integracion.api_tj@inpojuve.local',
            'name' => 'Integracion API_TJ',
        ]);

        $response = $this->authorizedPost();

        $response->assertCreated()
            ->assertJsonPath('accepted', true)
            ->assertJsonPath('status', 'created')
            ->assertJsonPath('external_request_id', 'API-TJ-STG-2001');

        $beneficiarioId = $response->json('beneficiario_id');

        $this->assertDatabaseHas('beneficiarios', [
            'id' => $beneficiarioId,
            'curp' => 'PELJ000101HMNRRS09',
            'municipio_id' => $this->municipio->id,
            'seccion_id' => $this->seccion->id,
        ]);

        $request = IntegrationInboundRequest::query()
            ->where('external_request_id', 'API-TJ-STG-2001')
            ->firstOrFail();

        $this->assertSame(IntegrationInboundRequest::STATUS_ACCEPTED, $request->status);
        $this->assertNotEmpty($request->request_payload_encrypted);
        $this->assertSame('created', $request->response_body['status'] ?? null);
    }

    public function test_same_external_request_id_returns_already_processed(): void
    {
        User::factory()->create([
            'email' => 'integracion.api_tj@inpojuve.local',
            'name' => 'Integracion API_TJ',
        ]);

        $firstResponse = $this->authorizedPost();
        $beneficiarioId = $firstResponse->json('beneficiario_id');

        $secondResponse = $this->authorizedPost([], (string) Str::uuid());

        $secondResponse->assertOk()
            ->assertJsonPath('accepted', true)
            ->assertJsonPath('status', 'already_processed')
            ->assertJsonPath('beneficiario_id', $beneficiarioId);

        $this->assertDatabaseCount('beneficiarios', 1);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function authorizedPost(array $overrides = [], ?string $jti = null)
    {
        return $this->withHeader('Authorization', 'Bearer '.$this->makeToken($jti))
            ->postJson('/api/v1/integrations/api-tj/staging/accept', $this->makePayload($overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function makePayload(array $overrides = []): array
    {
        return array_replace_recursive([
            'external_request_id' => 'API-TJ-STG-2001',
            'source' => 'api_tj',
            'beneficiario' => [
                'folio_tarjeta' => 'FOL-2001',
                'nombre' => 'ANA',
                'apellido_paterno' => 'PEREZ',
                'apellido_materno' => 'LOPEZ',
                'curp' => 'PELJ000101HMNRRS09',
                'fecha_nacimiento' => '2000-01-01',
                'sexo' => 'F',
                'discapacidad' => false,
                'id_ine' => '012345678901234567',
                'telefono' => '6141234567',
                'domicilio' => [
                    'calle' => 'AV PRINCIPAL',
                    'numero_ext' => '100',
                    'numero_int' => '2',
                    'colonia' => 'CENTRO',
                    'municipio_id' => $this->municipio->id,
                    'codigo_postal' => '31000',
                    'seccional' => $this->seccion->seccional,
                ],
            ],
        ], $overrides);
    }

    private function seedClient(): void
    {
        $client = IntegrationClient::query()->create([
            'id' => (string) Str::uuid(),
            'client_code' => 'api_tj',
            'name' => 'API Tarjeta Joven',
            'status' => IntegrationClient::STATUS_ACTIVE,
            'allowed_scopes' => ['beneficiarios.staging.push'],
        ]);

        IntegrationClientKey::query()->create([
            'id' => (string) Str::uuid(),
            'client_id' => $client->id,
            'kid' => 'api-tj-current',
            'public_key' => 'test-public-key',
            'status' => IntegrationClientKey::STATUS_ACTIVE,
        ]);
    }

    private function makeToken(?string $jti = null): string
    {
        return app(JwtCodec::class)->encode([
            'iss' => 'api_tj',
            'sub' => 'api_tj',
            'aud' => 'sys_ipj',
            'scope' => 'beneficiarios.staging.push',
            'jti' => $jti ?: (string) Str::uuid(),
            'iat' => now()->timestamp,
            'exp' => now()->addMinutes(5)->timestamp,
        ], [
            'alg' => 'RS256',
            'typ' => 'JWT',
            'kid' => 'api-tj-current',
        ], 'unused-private-key');
    }
}
