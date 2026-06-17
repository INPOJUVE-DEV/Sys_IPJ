<?php

namespace Tests\Feature;

use App\Models\Integrations\IntegrationClient;
use App\Models\Integrations\IntegrationClientKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Tests\TestCase;

class IntegrationsKeysUpsertCommandTest extends TestCase
{
    use RefreshDatabase;

    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempDir = storage_path('framework/testing/integration-keys-'.Str::uuid());
        if (! is_dir($this->tempDir)) {
            mkdir($this->tempDir, 0777, true);
        }
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempDir)) {
            foreach (glob($this->tempDir.DIRECTORY_SEPARATOR.'*') ?: [] as $file) {
                @unlink($file);
            }
            @rmdir($this->tempDir);
        }

        parent::tearDown();
    }

    public function test_it_creates_a_key_for_an_existing_integration_client(): void
    {
        $client = IntegrationClient::query()->create([
            'id' => (string) Str::uuid(),
            'client_code' => 'api_tj',
            'name' => 'API Tarjeta Joven',
            'status' => IntegrationClient::STATUS_ACTIVE,
            'allowed_scopes' => ['beneficiarios.staging.push'],
        ]);

        $publicKeyPath = $this->tempDir.DIRECTORY_SEPARATOR.'api_tj.pub';
        file_put_contents($publicKeyPath, "-----BEGIN PUBLIC KEY-----\nTEST-KEY-ONE\n-----END PUBLIC KEY-----\n");

        $code = Artisan::call('integrations:keys:upsert', [
            'client_code' => 'api_tj',
            'kid' => 'api-tj-current',
            'public_key_path' => $publicKeyPath,
        ]);

        $this->assertSame(0, $code);
        $this->assertDatabaseHas('integration_client_keys', [
            'client_id' => $client->id,
            'kid' => 'api-tj-current',
            'status' => IntegrationClientKey::STATUS_ACTIVE,
        ]);

        $key = IntegrationClientKey::query()
            ->where('client_id', $client->id)
            ->where('kid', 'api-tj-current')
            ->first();

        $this->assertNotNull($key);
        $this->assertSame("-----BEGIN PUBLIC KEY-----\nTEST-KEY-ONE\n-----END PUBLIC KEY-----", $key->public_key);
        $this->assertNotNull($key->valid_from);
    }

    public function test_it_updates_an_existing_key_for_the_same_client_and_kid(): void
    {
        $client = IntegrationClient::query()->create([
            'id' => (string) Str::uuid(),
            'client_code' => 'api_tj',
            'name' => 'API Tarjeta Joven',
            'status' => IntegrationClient::STATUS_ACTIVE,
            'allowed_scopes' => ['beneficiarios.staging.push'],
        ]);

        $existingKey = IntegrationClientKey::query()->create([
            'id' => (string) Str::uuid(),
            'client_id' => $client->id,
            'kid' => 'api-tj-current',
            'public_key' => "-----BEGIN PUBLIC KEY-----\nOLD-KEY\n-----END PUBLIC KEY-----",
            'status' => IntegrationClientKey::STATUS_INACTIVE,
            'valid_from' => now()->subDay(),
        ]);

        $publicKeyPath = $this->tempDir.DIRECTORY_SEPARATOR.'api_tj_updated.pub';
        file_put_contents($publicKeyPath, "-----BEGIN PUBLIC KEY-----\nNEW-KEY\n-----END PUBLIC KEY-----\n");

        $code = Artisan::call('integrations:keys:upsert', [
            'client_code' => 'api_tj',
            'kid' => 'api-tj-current',
            'public_key_path' => $publicKeyPath,
        ]);

        $this->assertSame(0, $code);

        $this->assertSame(1, IntegrationClientKey::query()->count());

        $updatedKey = $existingKey->fresh();
        $this->assertNotNull($updatedKey);
        $this->assertSame("-----BEGIN PUBLIC KEY-----\nNEW-KEY\n-----END PUBLIC KEY-----", $updatedKey->public_key);
        $this->assertSame(IntegrationClientKey::STATUS_ACTIVE, $updatedKey->status);
        $this->assertTrue($updatedKey->valid_from->equalTo($existingKey->valid_from));
    }

    public function test_it_fails_when_the_integration_client_does_not_exist(): void
    {
        $publicKeyPath = $this->tempDir.DIRECTORY_SEPARATOR.'missing-client.pub';
        file_put_contents($publicKeyPath, "-----BEGIN PUBLIC KEY-----\nTEST-KEY\n-----END PUBLIC KEY-----\n");

        $code = Artisan::call('integrations:keys:upsert', [
            'client_code' => 'missing_client',
            'kid' => 'api-tj-current',
            'public_key_path' => $publicKeyPath,
        ]);

        $this->assertSame(1, $code);
        $this->assertDatabaseCount('integration_client_keys', 0);
    }
}
