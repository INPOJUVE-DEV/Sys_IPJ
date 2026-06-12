<?php

use App\Models\Beneficiario;
use App\Models\Integrations\IntegrationInboundRequest;
use App\Models\Integrations\IntegrationSyncItem;
use App\Models\Integrations\IntegrationSyncRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    foreach (['admin', 'delegado', 'capturista', 'capturista_programas', 'skate_plaza'] as $role) {
        Role::firstOrCreate([
            'name' => $role,
            'guard_name' => 'web',
        ]);
    }

    config([
        'integrations.api_tj.integration_user_email' => 'integracion.api_tj@inpojuve.local',
        'integrations.payload_encryption_key' => 'test-inbound-payload-key',
        'integrations.outbound.base_url' => 'https://api-tj.test',
        'integrations.outbound.private_key_path' => __FILE__,
    ]);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
});

it('permite al admin consultar corridas e inbound requests de integracion', function () {
    $beneficiario = Beneficiario::query()->create([
        'id' => (string) Str::uuid(),
        'folio_tarjeta' => 'FOL-ADMIN-001',
        'nombre' => 'ANA',
        'apellido_paterno' => 'PEREZ',
        'apellido_materno' => 'LOPEZ',
        'curp' => 'PELJ000101HMNRRS09',
        'fecha_nacimiento' => '2000-01-01',
        'edad' => 24,
        'sexo' => 'F',
        'discapacidad' => false,
        'telefono' => '6141234567',
        'created_by' => $this->admin->uuid,
    ]);

    $run = IntegrationSyncRun::query()->create([
        'id' => (string) Str::uuid(),
        'target_system' => 'api_tj',
        'operation' => 'cardholders.sync',
        'status' => IntegrationSyncRun::STATUS_PARTIAL,
        'requested_by' => $this->admin->uuid,
        'total_items' => 1,
        'success_count' => 0,
        'failed_count' => 1,
        'skipped_count' => 0,
    ]);

    IntegrationSyncItem::query()->create([
        'id' => (string) Str::uuid(),
        'sync_run_id' => $run->id,
        'beneficiario_id' => $beneficiario->id,
        'payload_hash' => hash('sha256', 'payload'),
        'status' => IntegrationSyncItem::STATUS_ERROR,
        'response_code' => 401,
        'error_message' => 'Unauthorized',
    ]);

    $inboundRequest = IntegrationInboundRequest::query()->create([
        'id' => (string) Str::uuid(),
        'source_system' => 'api_tj',
        'external_request_id' => 'API-TJ-STG-3001',
        'operation' => 'beneficiarios.staging.accept',
        'request_hash' => hash('sha256', 'request'),
        'request_payload_encrypted' => 'ciphertext',
        'status' => IntegrationInboundRequest::STATUS_ACCEPTED,
        'response_code' => 201,
        'response_body' => ['status' => 'created'],
        'received_at' => now(),
        'processed_at' => now(),
    ]);

    $this->actingAs($this->admin)
        ->get(route('admin.integraciones.api_tj.sync-runs.index'))
        ->assertOk()
        ->assertSee('Disparar sync manual')
        ->assertSee('Readiness operativo');

    $this->actingAs($this->admin)
        ->get(route('admin.integraciones.api_tj.sync-runs.show', $run))
        ->assertOk()
        ->assertSee('Unauthorized')
        ->assertSee('PELJ000101HMNRRS09');

    $this->actingAs($this->admin)
        ->get(route('admin.integraciones.api_tj.inbound-requests.index'))
        ->assertOk()
        ->assertSee('API-TJ-STG-3001');

    $this->actingAs($this->admin)
        ->get(route('admin.integraciones.api_tj.inbound-requests.show', $inboundRequest))
        ->assertOk()
        ->assertSee('beneficiarios.staging.accept')
        ->assertSee('created');
});

it('permite al admin disparar una corrida manual compliant', function () {
    Queue::fake();

    $response = $this->actingAs($this->admin)
        ->post(route('admin.integraciones.api_tj.cardholders.sync'));

    $run = IntegrationSyncRun::query()->latest('created_at')->first();

    expect($run)->not->toBeNull();

    $response->assertRedirect(route('admin.integraciones.api_tj.sync-runs.show', $run));
    $this->assertDatabaseHas('integration_sync_runs', [
        'id' => $run->id,
        'target_system' => 'api_tj',
        'operation' => 'cardholders.sync',
    ]);
});

it('rechaza acceso a integraciones para usuarios no admin', function () {
    $capturista = User::factory()->create();
    $capturista->assignRole('capturista');

    $this->actingAs($capturista)
        ->get(route('admin.integraciones.api_tj.sync-runs.index'))
        ->assertForbidden();
});
