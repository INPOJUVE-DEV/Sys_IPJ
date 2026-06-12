<?php

namespace Tests\Feature;

use App\Models\Integrations\IntegrationInboundRequest;
use App\Services\Integrations\Inbound\InboundIdempotencyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InboundIdempotencyServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'integrations.payload_encryption_key' => 'test-inbound-payload-key',
        ]);
    }

    public function test_it_creates_a_new_received_request_with_encrypted_payload(): void
    {
        $request = DB::transaction(fn () => app(InboundIdempotencyService::class)->resolveOrCreate(
            'api_tj',
            'API-TJ-STG-1001',
            [
                'external_request_id' => 'API-TJ-STG-1001',
                'source' => 'api_tj',
                'beneficiario' => ['curp' => 'TEST000101HMNRRS01'],
            ],
        ));

        $this->assertSame(IntegrationInboundRequest::STATUS_RECEIVED, $request->status);
        $this->assertNotEmpty($request->request_payload_encrypted);
        $this->assertStringNotContainsString('API-TJ-STG-1001', (string) $request->request_payload_encrypted);
    }

    public function test_it_resets_rejected_requests_for_controlled_reprocess(): void
    {
        $request = DB::transaction(fn () => app(InboundIdempotencyService::class)->resolveOrCreate(
            'api_tj',
            'API-TJ-STG-1002',
            [
                'external_request_id' => 'API-TJ-STG-1002',
                'source' => 'api_tj',
                'beneficiario' => ['curp' => 'TEST000101HMNRRS02'],
            ],
        ));

        DB::transaction(fn () => app(InboundIdempotencyService::class)->markRejected(
            $request->fresh(),
            422,
            ['status' => 'validation_error'],
            'Validation failed.',
        ));

        $reloaded = DB::transaction(fn () => app(InboundIdempotencyService::class)->resolveOrCreate(
            'api_tj',
            'API-TJ-STG-1002',
            [
                'external_request_id' => 'API-TJ-STG-1002',
                'source' => 'api_tj',
                'beneficiario' => ['curp' => 'TEST000101HMNRRS02'],
            ],
        ));

        $this->assertSame(IntegrationInboundRequest::STATUS_RECEIVED, $reloaded->status);
        $this->assertNull($reloaded->response_code);
        $this->assertNull($reloaded->error_message);
    }
}
