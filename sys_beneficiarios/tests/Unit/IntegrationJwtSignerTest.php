<?php

namespace Tests\Unit;

use App\Services\Integrations\Security\Contracts\JwtCodec;
use App\Services\Integrations\Security\IntegrationJwtSigner;
use Tests\Support\Integrations\RecordingJwtCodec;
use Tests\TestCase;

class IntegrationJwtSignerTest extends TestCase
{
    public function test_signer_builds_expected_claims_and_headers(): void
    {
        $privateKeyPath = tempnam(sys_get_temp_dir(), 'integration-jwt-');
        file_put_contents($privateKeyPath, 'test-private-key');

        $codec = new RecordingJwtCodec();
        $this->app->instance(JwtCodec::class, $codec);

        config([
            'integrations.outbound.issuer' => 'sys_ipj',
            'integrations.outbound.subject' => 'sys_ipj',
            'integrations.outbound.kid' => 'sys_ipj-current',
            'integrations.outbound.private_key_path' => $privateKeyPath,
            'integrations.outbound.ttl_seconds' => 600,
        ]);

        $token = app(IntegrationJwtSigner::class)->makeToken('api_tj', 'cardholders.sync');

        $this->assertSame('encoded.integration.jwt', $token);
        $this->assertSame('sys_ipj', $codec->payload['iss']);
        $this->assertSame('sys_ipj', $codec->payload['sub']);
        $this->assertSame('api_tj', $codec->payload['aud']);
        $this->assertSame('cardholders.sync', $codec->payload['scope']);
        $this->assertArrayHasKey('jti', $codec->payload);
        $this->assertArrayHasKey('iat', $codec->payload);
        $this->assertArrayHasKey('exp', $codec->payload);
        $this->assertSame('RS256', $codec->headers['alg']);
        $this->assertSame('JWT', $codec->headers['typ']);
        $this->assertSame('sys_ipj-current', $codec->headers['kid']);
        $this->assertSame('test-private-key', $codec->privateKey);

        @unlink($privateKeyPath);
    }
}
