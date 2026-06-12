<?php

namespace Tests\Feature;

use Database\Seeders\IntegrationClientSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class IntegrationPersistencePhaseOneTest extends TestCase
{
    use RefreshDatabase;

    public function test_phase_one_creates_integration_tables_and_seeds_initial_clients(): void
    {
        $this->seed(IntegrationClientSeeder::class);

        $this->assertTrue(Schema::hasTable('integration_clients'));
        $this->assertTrue(Schema::hasTable('integration_client_keys'));
        $this->assertTrue(Schema::hasTable('integration_jti_logs'));
        $this->assertTrue(Schema::hasTable('integration_sync_runs'));
        $this->assertTrue(Schema::hasTable('integration_sync_items'));
        $this->assertTrue(Schema::hasTable('integration_inbound_requests'));

        $this->assertDatabaseHas('integration_clients', [
            'client_code' => 'api_tj',
            'name' => 'API Tarjeta Joven',
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('integration_clients', [
            'client_code' => 'sys_ipj',
            'name' => 'Sys_IPJ',
            'status' => 'active',
        ]);
    }

    public function test_phase_one_does_not_invade_beneficiarios_schema(): void
    {
        $this->assertFalse(Schema::hasColumn('beneficiarios', 'source_system'));
        $this->assertFalse(Schema::hasColumn('beneficiarios', 'source_external_request_id'));
        $this->assertFalse(Schema::hasColumn('beneficiarios', 'curp_hash'));
        $this->assertFalse(Schema::hasColumn('beneficiarios', 'api_tj_sync_status'));
        $this->assertFalse(Schema::hasColumn('beneficiarios', 'api_tj_sync_attempts'));
        $this->assertFalse(Schema::hasColumn('beneficiarios', 'api_tj_last_synced_at'));
        $this->assertFalse(Schema::hasColumn('beneficiarios', 'api_tj_last_sync_error'));

        $columns = collect(DB::select("PRAGMA table_info('beneficiarios')"))->keyBy('name');
        $this->assertSame(1, (int) $columns['created_by']->notnull);
    }
}
