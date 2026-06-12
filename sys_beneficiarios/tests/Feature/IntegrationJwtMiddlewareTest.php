<?php

namespace Tests\Feature;

use App\Models\Integrations\IntegrationClient;
use App\Models\Integrations\IntegrationClientKey;
use App\Services\Integrations\Security\Contracts\JwtCodec;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\Support\Integrations\TestJwtCodec;
use Tests\TestCase;

class IntegrationJwtMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'integrations.inbound.audience' => 'sys_ipj',
            'integrations.inbound.max_token_ttl_seconds' => 600,
        ]);

        $this->app->instance(JwtCodec::class, new TestJwtCodec());

        Route::middleware(['api', 'integration.jwt:beneficiarios.staging.push'])
            ->get('/_test/integration-jwt', function (Request $request) {
                $context = $request->attributes->get('integration_auth');

                return response()->json([
                    'client_code' => $context->client->client_code,
                    'jti' => $context->jti,
                ]);
            });
    }

    public function test_valid_token_passes_through_middleware(): void
    {
        $this->seedClient();
        $jti = (string) Str::uuid();

        $response = $this->withHeader('Authorization', 'Bearer '.$this->makeToken([
            'iss' => 'api_tj',
            'sub' => 'api_tj',
            'aud' => 'sys_ipj',
            'scope' => 'beneficiarios.staging.push',
            'jti' => $jti,
            'iat' => now()->timestamp,
            'exp' => now()->addMinutes(5)->timestamp,
        ]))->getJson('/_test/integration-jwt');

        $response->assertOk()
            ->assertJsonPath('client_code', 'api_tj')
            ->assertJsonPath('jti', $jti);
    }

    public function test_replayed_jti_is_rejected(): void
    {
        $this->seedClient();
        $jti = (string) Str::uuid();
        $token = $this->makeToken([
            'iss' => 'api_tj',
            'sub' => 'api_tj',
            'aud' => 'sys_ipj',
            'scope' => 'beneficiarios.staging.push',
            'jti' => $jti,
            'iat' => now()->timestamp,
            'exp' => now()->addMinutes(5)->timestamp,
        ]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/_test/integration-jwt')
            ->assertOk();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/_test/integration-jwt')
            ->assertUnauthorized()
            ->assertJson([
                'accepted' => false,
                'status' => 'unauthorized',
                'message' => 'Token de integracion invalido',
            ]);
    }

    public function test_missing_scope_is_rejected(): void
    {
        $this->seedClient();

        $response = $this->withHeader('Authorization', 'Bearer '.$this->makeToken([
            'iss' => 'api_tj',
            'sub' => 'api_tj',
            'aud' => 'sys_ipj',
            'scope' => 'cardholders.sync',
            'jti' => (string) Str::uuid(),
            'iat' => now()->timestamp,
            'exp' => now()->addMinutes(5)->timestamp,
        ]))->getJson('/_test/integration-jwt');

        $response->assertForbidden()
            ->assertJson([
                'accepted' => false,
                'status' => 'forbidden',
                'message' => 'Permisos insuficientes',
            ]);
    }

    public function test_malformed_token_returns_expected_integration_json(): void
    {
        $this->seedClient();

        $this->withHeader('Authorization', 'Bearer malformed.token')
            ->getJson('/_test/integration-jwt')
            ->assertUnauthorized()
            ->assertJson([
                'accepted' => false,
                'status' => 'unauthorized',
                'message' => 'Token de integracion invalido',
            ]);
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

    /**
     * @param  array<string, mixed>  $payload
     */
    private function makeToken(array $payload): string
    {
        return app(JwtCodec::class)->encode($payload, [
            'alg' => 'RS256',
            'typ' => 'JWT',
            'kid' => 'api-tj-current',
        ], 'unused-private-key');
    }
}
