<?php

namespace Tests\Feature;

use App\Jobs\Integrations\ApiTj\RunCardholderSyncJob;
use App\Models\Beneficiario;
use App\Models\Integrations\IntegrationSyncItem;
use App\Models\Integrations\IntegrationSyncRun;
use App\Models\Municipio;
use App\Models\Oficina;
use App\Models\Seccion;
use App\Models\User;
use App\Services\Integrations\ApiTj\CardholderSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Mockery\MockInterface;
use Tests\TestCase;

class BeneficiarioStoreSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RoleSeeder::class);
        $this->seed(\Database\Seeders\OficinaSeeder::class);

        config([
            'integrations.outbound.hash_secret' => 'test-curp-secret',
        ]);
    }

    public function test_store_queues_a_sync_run_for_only_the_new_beneficiary(): void
    {
        Queue::fake();

        [$office, $municipio, $seccion] = $this->officeAndSeccion();
        $user = $this->capturistaForOffice($office);
        $existing = $this->makeExistingBeneficiario($user, $municipio, $seccion, 'PEPG000101HDFLRNA2', 'TJ-EXISTING-001');

        $response = $this->actingAs($user)->post(route('beneficiarios.store'), $this->validPayload($municipio, [
            'curp' => 'PEPJ000101HDFLRNA1',
            'folio_tarjeta' => 'TJ-NEW-001',
        ]));

        $response->assertRedirect(route('beneficiarios.create'));
        $response->assertSessionHas('status', 'Registrado');

        $beneficiario = Beneficiario::query()->where('curp', 'PEPJ000101HDFLRNA1')->firstOrFail();
        $this->assertDatabaseCount('integration_sync_runs', 1);
        $this->assertDatabaseCount('integration_sync_items', 1);

        $run = IntegrationSyncRun::query()->firstOrFail();
        $item = IntegrationSyncItem::query()->firstOrFail();

        $this->assertSame('api_tj', $run->target_system);
        $this->assertSame('cardholders.sync', $run->operation);
        $this->assertSame($user->uuid, $run->requested_by);
        $this->assertSame(1, $run->total_items);
        $this->assertSame($beneficiario->id, $item->beneficiario_id);
        $this->assertNotSame($existing->id, $item->beneficiario_id);

        Queue::assertPushed(RunCardholderSyncJob::class, fn (RunCardholderSyncJob $job) => $job->syncRunId === $run->id);
    }

    public function test_store_keeps_the_beneficiary_when_sync_queue_fails(): void
    {
        Log::spy();

        [$office, $municipio] = $this->officeAndSeccion();
        $user = $this->capturistaForOffice($office);

        $this->mock(CardholderSyncService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('queueBeneficiario')
                ->once()
                ->andThrow(new \RuntimeException('Queue unavailable'));
        });

        $response = $this->actingAs($user)->post(route('beneficiarios.store'), $this->validPayload($municipio, [
            'curp' => 'PEPK000101HDFLRNA3',
        ]));

        $response->assertRedirect(route('beneficiarios.create'));
        $response->assertSessionHas('status', 'Registrado. La sincronizacion con API_TJ quedo pendiente de revisar.');

        $beneficiario = Beneficiario::query()->where('curp', 'PEPK000101HDFLRNA3')->first();

        $this->assertNotNull($beneficiario);
        $this->assertDatabaseCount('integration_sync_runs', 0);

        Log::shouldHaveReceived('error')
            ->once()
            ->withArgs(function (string $message, array $context) use ($beneficiario, $user): bool {
                return $message === 'Error al programar sincronizacion de beneficiario hacia API_TJ'
                    && $context['beneficiario_id'] === $beneficiario?->id
                    && $context['user_id'] === $user->uuid
                    && $context['message'] === 'Queue unavailable';
            });
    }

    public function test_store_marks_the_sync_item_as_skipped_when_card_data_is_missing(): void
    {
        Queue::fake();

        [$office, $municipio] = $this->officeAndSeccion();
        $user = $this->capturistaForOffice($office);

        $response = $this->actingAs($user)->post(route('beneficiarios.store'), $this->validPayload($municipio, [
            'curp' => 'PEPL000101HDFLRNA4',
            'folio_tarjeta' => null,
        ]));

        $response->assertRedirect(route('beneficiarios.create'));

        $beneficiario = Beneficiario::query()->where('curp', 'PEPL000101HDFLRNA4')->firstOrFail();
        $run = IntegrationSyncRun::query()->firstOrFail();
        $item = IntegrationSyncItem::query()->firstOrFail();

        $this->assertSame(IntegrationSyncRun::STATUS_SUCCESS, $run->status);
        $this->assertSame(1, $run->total_items);
        $this->assertSame(1, $run->skipped_count);
        $this->assertSame($beneficiario->id, $item->beneficiario_id);
        $this->assertSame(IntegrationSyncItem::STATUS_SKIPPED, $item->status);

        Queue::assertNotPushed(RunCardholderSyncJob::class);
    }

    private function officeAndSeccion(): array
    {
        $office = Oficina::where('tipo', Oficina::TIPO_DELEGACION)->orderBy('id')->firstOrFail();
        $municipio = Municipio::updateOrCreate(
            ['clave' => 1],
            ['nombre' => 'Test', 'oficina_id' => $office->id]
        );
        $seccion = Seccion::updateOrCreate(
            ['seccional' => '12345'],
            ['municipio_id' => $municipio->id, 'distrito_local' => 'DL', 'distrito_federal' => 'DF']
        );

        return [$office, $municipio, $seccion];
    }

    private function capturistaForOffice(Oficina $office): User
    {
        $user = User::factory()->create(['oficina_id' => $office->id]);
        $user->assignRole('capturista');

        return $user;
    }

    private function validPayload(Municipio $municipio, array $overrides = []): array
    {
        return array_replace_recursive([
            'nombre' => 'Juan',
            'apellido_paterno' => 'Perez',
            'apellido_materno' => 'Lopez',
            'curp' => 'PEPJ000101HDFLRNA1',
            'fecha_nacimiento' => '2000-01-01',
            'sexo' => 'M',
            'discapacidad' => '0',
            'id_ine' => '123450001122334455',
            'telefono' => '5512345678',
            'domicilio' => [
                'calle' => 'Calle',
                'numero_ext' => '1',
                'colonia' => 'Centro',
                'municipio_id' => $municipio->id,
                'codigo_postal' => '01234',
            ],
        ], $overrides);
    }

    private function makeExistingBeneficiario(
        User $actor,
        Municipio $municipio,
        Seccion $seccion,
        string $curp,
        ?string $folioTarjeta,
    ): Beneficiario {
        return Beneficiario::query()->create([
            'id' => (string) Str::uuid(),
            'folio_tarjeta' => $folioTarjeta,
            'nombre' => 'Persona',
            'apellido_paterno' => 'Existente',
            'apellido_materno' => 'Prueba',
            'curp' => $curp,
            'fecha_nacimiento' => '2000-01-01',
            'edad' => 24,
            'sexo' => 'M',
            'discapacidad' => false,
            'id_ine' => '123450001122334456',
            'telefono' => '5512345679',
            'municipio_id' => $municipio->id,
            'seccion_id' => $seccion->id,
            'created_by' => $actor->uuid,
        ]);
    }
}
