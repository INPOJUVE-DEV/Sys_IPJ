<?php

namespace Tests\Feature;

use App\Services\Integrations\ApiTj\ApiTjTechnicalUserResolver;
use Database\Seeders\IntegrationTechnicalUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class IntegrationTechnicalUserTest extends TestCase
{
    use RefreshDatabase;

    public function test_integration_technical_user_seeder_creates_expected_user(): void
    {
        config([
            'integrations.api_tj.integration_user_email' => 'integracion.api_tj@inpojuve.local',
        ]);

        $this->seed(IntegrationTechnicalUserSeeder::class);

        $this->assertDatabaseHas('users', [
            'email' => 'integracion.api_tj@inpojuve.local',
            'name' => 'Integracion API_TJ',
        ]);
    }

    public function test_api_tj_technical_user_resolver_returns_seeded_user(): void
    {
        config([
            'integrations.api_tj.integration_user_email' => 'integracion.api_tj@inpojuve.local',
        ]);

        $this->seed(IntegrationTechnicalUserSeeder::class);

        $user = app(ApiTjTechnicalUserResolver::class)->resolve();

        $this->assertSame('integracion.api_tj@inpojuve.local', $user->email);
        $this->assertSame('Integracion API_TJ', $user->name);
    }

    public function test_api_tj_technical_user_resolver_fails_when_user_does_not_exist(): void
    {
        config([
            'integrations.api_tj.integration_user_email' => 'missing.api_tj@inpojuve.local',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('missing.api_tj@inpojuve.local');

        app(ApiTjTechnicalUserResolver::class)->resolve();
    }
}
