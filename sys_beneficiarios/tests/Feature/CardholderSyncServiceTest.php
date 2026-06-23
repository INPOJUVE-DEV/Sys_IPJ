<?php

namespace Tests\Feature;

use App\Jobs\Integrations\ApiTj\RunCardholderSyncJob;
use App\Models\Beneficiario;
use App\Models\Integrations\IntegrationSyncItem;
use App\Models\Integrations\IntegrationSyncRun;
use App\Models\Tarjeta;
use App\Models\User;
use App\Services\Integrations\ApiTj\CardholderSyncService;
use App\Services\Integrations\Security\Contracts\JwtCodec;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\Support\Integrations\RecordingJwtCodec;
use Tests\TestCase;

class CardholderSyncServiceTest extends TestCase
{
    use RefreshDatabase;

    private string $privateKeyPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->privateKeyPath = tempnam(sys_get_temp_dir(), 'sys-ipj-jwt-');
        file_put_contents($this->privateKeyPath, 'test-private-key');

        config([
            'integrations.outbound.base_url' => 'https://api-tj.test',
            'integrations.outbound.audience' => 'api_tj',
            'integrations.outbound.scope' => 'cardholders.sync',
            'integrations.outbound.issuer' => 'sys_ipj',
            'integrations.outbound.subject' => 'sys_ipj',
            'integrations.outbound.kid' => 'sys-ipj-current',
            'integrations.outbound.private_key_path' => $this->privateKeyPath,
            'integrations.outbound.hash_secret' => 'test-curp-secret',
            'integrations.outbound.timeout_seconds' => 10,
            'integrations.outbound.batch_size' => 50,
        ]);

        $this->app->instance(JwtCodec::class, new RecordingJwtCodec());
    }

    protected function tearDown(): void
    {
        @unlink($this->privateKeyPath);

        parent::tearDown();
    }

    public function test_queue_creates_auditable_run_and_marks_missing_card_numbers_as_skipped(): void
    {
        Queue::fake();

        $actor = User::factory()->create();
        $this->makeBeneficiarioWithConsumedCard($actor, 'PESA000101HSPABC01', 'LEG-001', 'TJ-001');
        $this->makeBeneficiario($actor, 'PESB000101HSPABC02', 'LEG-002');
        $this->makeBeneficiario($actor, 'PESC000101HSPABC03', null);

        $run = app(CardholderSyncService::class)->queue($actor);

        $this->assertSame(IntegrationSyncRun::STATUS_QUEUED, $run->status);
        $this->assertSame(3, $run->total_items);
        $this->assertSame(1, $run->skipped_count);
        $this->assertDatabaseHas('integration_sync_items', [
            'sync_run_id' => $run->id,
            'status' => IntegrationSyncItem::STATUS_SKIPPED,
        ]);

        Queue::assertPushed(RunCardholderSyncJob::class, fn (RunCardholderSyncJob $job) => $job->syncRunId === $run->id);
    }

    public function test_queue_beneficiario_creates_a_single_item_for_the_requested_beneficiary(): void
    {
        Queue::fake();

        $actor = User::factory()->create();
        $target = $this->makeBeneficiario($actor, 'PESD000101HSPABC14', 'LEG-ONLY');
        $other = $this->makeBeneficiarioWithConsumedCard($actor, 'PESE000101HSPABC15', 'LEG-OTHER', 'TJ-OTHER');

        $run = app(CardholderSyncService::class)->queueBeneficiario($target, $actor);

        $this->assertSame(IntegrationSyncRun::STATUS_QUEUED, $run->status);
        $this->assertSame(1, $run->total_items);
        $this->assertCount(1, $run->items);
        $this->assertSame($target->id, $run->items->sole()->beneficiario_id);
        $this->assertDatabaseMissing('integration_sync_items', [
            'sync_run_id' => $run->id,
            'beneficiario_id' => $other->id,
        ]);

        Queue::assertPushed(RunCardholderSyncJob::class, fn (RunCardholderSyncJob $job) => $job->syncRunId === $run->id);
    }

    public function test_queue_beneficiario_marks_missing_card_numbers_as_skipped_without_dispatching_a_job(): void
    {
        Queue::fake();

        $actor = User::factory()->create();
        $beneficiario = $this->makeBeneficiario($actor, 'PESF000101HSPABC16', null);

        $run = app(CardholderSyncService::class)->queueBeneficiario($beneficiario, $actor);

        $this->assertSame(IntegrationSyncRun::STATUS_SUCCESS, $run->status);
        $this->assertSame(1, $run->total_items);
        $this->assertSame(1, $run->skipped_count);
        $this->assertCount(1, $run->items);
        $this->assertSame(IntegrationSyncItem::STATUS_SKIPPED, $run->items->sole()->status);
        $this->assertSame($beneficiario->id, $run->items->sole()->beneficiario_id);

        Queue::assertNotPushed(RunCardholderSyncJob::class);
    }

    public function test_run_records_partial_success_when_some_items_are_skipped_locally(): void
    {
        Queue::fake();
        Http::fake([
            'https://api-tj.test/*' => Http::response([
                'accepted' => true,
                'results' => [
                    ['index' => 0, 'status' => 'upserted', 'tarjeta_numero' => 'TJ-001'],
                    ['index' => 1, 'status' => 'upserted', 'tarjeta_numero' => 'LEG-002'],
                ],
            ], 200),
        ]);

        $actor = User::factory()->create();
        $this->makeBeneficiarioWithConsumedCard($actor, 'PETA000101HSPABC04', 'LEG-001', 'TJ-001');
        $this->makeBeneficiario($actor, 'PETB000101HSPABC05', 'LEG-002');
        $this->makeBeneficiario($actor, 'PETC000101HSPABC06', null);

        $service = app(CardholderSyncService::class);
        $run = $service->queue($actor);
        $service->run($run->fresh());

        $run->refresh();

        $this->assertSame(IntegrationSyncRun::STATUS_PARTIAL, $run->status);
        $this->assertSame(2, $run->success_count);
        $this->assertSame(0, $run->failed_count);
        $this->assertSame(1, $run->skipped_count);
        $this->assertDatabaseCount('integration_sync_items', 3);
        $this->assertDatabaseHas('integration_sync_items', [
            'sync_run_id' => $run->id,
            'status' => IntegrationSyncItem::STATUS_ACCEPTED,
        ]);

        Http::assertSent(function ($request) {
            $body = $request->data();
            $items = $body['items'] ?? [];

            return str_ends_with($request->url(), '/api/v1/cardholders/sync')
                && str_contains($request->body(), '"tarjeta_numero":"TJ-001"')
                && str_contains($request->body(), '"tarjeta_numero":"LEG-002"')
                && str_contains($request->body(), '"sync_id":"SYS-IPJ-')
                && ($items[0]['nombres'] ?? null) === 'TEST'
                && ($items[0]['apellido'] ?? null) === 'PERSONA SYNC'
                && array_key_exists('municipio_id', $items[0])
                && ! array_key_exists('telefono', $items[0])
                && ! array_key_exists('domicilio', $items[0])
                && ! array_key_exists('id_ine', $items[0])
                && ! array_key_exists('fecha_nacimiento', $items[0])
                && ! array_key_exists('sexo', $items[0]);
        });
    }

    public function test_run_marks_the_sync_as_failed_when_api_tj_rejects_the_request(): void
    {
        Queue::fake();
        Http::fake([
            'https://api-tj.test/*' => Http::response([
                'message' => 'Unauthorized',
            ], 401),
        ]);

        $actor = User::factory()->create();
        $this->makeBeneficiarioWithConsumedCard($actor, 'PEUA000101HSPABC07', 'LEG-001', 'TJ-401');

        $service = app(CardholderSyncService::class);
        $run = $service->queue($actor);
        $service->run($run->fresh());

        $run->refresh();
        $item = $run->items()->firstOrFail();

        $this->assertSame(IntegrationSyncRun::STATUS_FAILED, $run->status);
        $this->assertSame(0, $run->success_count);
        $this->assertSame(1, $run->failed_count);
        $this->assertSame(IntegrationSyncItem::STATUS_ERROR, $item->status);
        $this->assertSame(401, $item->response_code);
        $this->assertSame('Unauthorized', $run->error_message);
    }

    public function test_run_marks_rejected_items_as_partial_response(): void
    {
        Queue::fake();
        Http::fake([
            'https://api-tj.test/*' => Http::response([
                'accepted' => true,
                'results' => [
                    ['index' => 0, 'status' => 'upserted', 'tarjeta_numero' => 'TJ-OK-001'],
                    ['index' => 1, 'status' => 'rejected', 'message' => 'Duplicate cardholder'],
                ],
            ], 200),
        ]);

        $actor = User::factory()->create();
        $this->makeBeneficiarioWithConsumedCard($actor, 'PEVA000101HSPABC08', 'LEG-001', 'TJ-OK-001');
        $this->makeBeneficiario($actor, 'PEVB000101HSPABC09', 'LEG-REJECT');

        $service = app(CardholderSyncService::class);
        $run = $service->queue($actor);
        $service->run($run->fresh());

        $run->refresh();

        $this->assertSame(IntegrationSyncRun::STATUS_PARTIAL, $run->status);
        $this->assertSame(1, $run->success_count);
        $this->assertSame(1, $run->failed_count);
        $this->assertDatabaseHas('integration_sync_items', [
            'sync_run_id' => $run->id,
            'status' => IntegrationSyncItem::STATUS_REJECTED,
            'error_message' => 'Duplicate cardholder',
        ]);
    }

    public function test_run_maps_conflict_results_as_rejected_items(): void
    {
        Queue::fake();
        Http::fake([
            'https://api-tj.test/*' => Http::response([
                'accepted' => true,
                'results' => [
                    ['index' => 0, 'status' => 'accepted', 'action' => 'inserted'],
                    ['index' => 1, 'status' => 'conflict', 'reason' => 'tarjeta_numero ya existe con otro curp_hash'],
                ],
            ], 200),
        ]);

        $actor = User::factory()->create();
        $this->makeBeneficiarioWithConsumedCard($actor, 'PEWA000101HSPABC10', 'LEG-001', 'TJ-CONFLICT-001');
        $this->makeBeneficiario($actor, 'PEWB000101HSPABC11', 'LEG-CONFLICT');

        $service = app(CardholderSyncService::class);
        $run = $service->queue($actor);
        $service->run($run->fresh());

        $run->refresh();

        $this->assertSame(IntegrationSyncRun::STATUS_PARTIAL, $run->status);
        $this->assertSame(1, $run->success_count);
        $this->assertSame(1, $run->failed_count);
        $this->assertSame(0, $run->skipped_count);
        $this->assertDatabaseHas('integration_sync_items', [
            'sync_run_id' => $run->id,
            'status' => IntegrationSyncItem::STATUS_REJECTED,
            'error_message' => 'tarjeta_numero ya existe con otro curp_hash',
        ]);
    }

    public function test_run_maps_skipped_results_as_skipped_items(): void
    {
        Queue::fake();
        Http::fake([
            'https://api-tj.test/*' => Http::response([
                'accepted' => true,
                'results' => [
                    ['index' => 0, 'status' => 'accepted', 'action' => 'updated'],
                    ['index' => 1, 'status' => 'skipped', 'reason' => 'curp_hash invalido'],
                ],
            ], 200),
        ]);

        $actor = User::factory()->create();
        $this->makeBeneficiarioWithConsumedCard($actor, 'PEXA000101HSPABC12', 'LEG-001', 'TJ-SKIPPED-001');
        $this->makeBeneficiario($actor, 'PEXB000101HSPABC13', 'LEG-SKIPPED');

        $service = app(CardholderSyncService::class);
        $run = $service->queue($actor);
        $service->run($run->fresh());

        $run->refresh();

        $this->assertSame(IntegrationSyncRun::STATUS_PARTIAL, $run->status);
        $this->assertSame(1, $run->success_count);
        $this->assertSame(0, $run->failed_count);
        $this->assertSame(1, $run->skipped_count);
        $this->assertDatabaseHas('integration_sync_items', [
            'sync_run_id' => $run->id,
            'status' => IntegrationSyncItem::STATUS_SKIPPED,
            'error_message' => 'curp_hash invalido',
        ]);
    }

    private function makeBeneficiario(User $actor, string $curp, ?string $folioTarjeta): Beneficiario
    {
        return Beneficiario::query()->create([
            'id' => (string) Str::uuid(),
            'folio_tarjeta' => $folioTarjeta,
            'nombre' => 'TEST',
            'apellido_paterno' => 'PERSONA',
            'apellido_materno' => 'SYNC',
            'curp' => $curp,
            'fecha_nacimiento' => '2000-01-01',
            'edad' => 24,
            'sexo' => 'M',
            'discapacidad' => false,
            'telefono' => '4441234567',
            'municipio_id' => null,
            'seccion_id' => null,
            'created_by' => $actor->uuid,
        ]);
    }

    private function makeBeneficiarioWithConsumedCard(User $actor, string $curp, ?string $folioTarjeta, string $tarjetaFolio): Beneficiario
    {
        $beneficiario = $this->makeBeneficiario($actor, $curp, $folioTarjeta);

        $tarjeta = Tarjeta::query()->create([
            'id' => (string) Str::uuid(),
            'folio' => $tarjetaFolio,
            'estatus' => Tarjeta::STATUS_CONSUMIDA,
            'beneficiario_id' => $beneficiario->id,
        ]);

        $beneficiario->forceFill(['tarjeta_id' => $tarjeta->id])->save();

        return $beneficiario->fresh('tarjeta');
    }
}
