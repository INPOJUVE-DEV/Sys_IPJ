<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class IntegrationMigrationPhaseSixTest extends TestCase
{
    /**
     * @var array<int, string>
     */
    private array $integrationTables = [
        'integration_clients',
        'integration_client_keys',
        'integration_jti_logs',
        'integration_sync_runs',
        'integration_sync_items',
        'integration_inbound_requests',
    ];

    /**
     * @var array<int, string>
     */
    private array $integrationMigrationFiles = [
        '2026_06_10_000100_create_integration_clients_table.php',
        '2026_06_10_000200_create_integration_client_keys_table.php',
        '2026_06_10_000300_create_integration_jti_logs_table.php',
        '2026_06_10_000400_create_integration_sync_runs_table.php',
        '2026_06_10_000500_create_integration_sync_items_table.php',
        '2026_06_10_000600_create_integration_inbound_requests_table.php',
    ];

    public function test_migrate_fresh_builds_integration_tables_without_invading_core_schema(): void
    {
        $this->runMigrateFresh();

        foreach ($this->integrationTables as $table) {
            $this->assertTrue(Schema::hasTable($table), "Expected table [{$table}] to exist after migrate:fresh.");
        }

        $this->assertTrue(Schema::hasTable('beneficiarios'));
        $this->assertTrue(Schema::hasTable('domicilios'));
        $this->assertTrue(Schema::hasTable('users'));

        $this->assertFalse(Schema::hasColumn('beneficiarios', 'source_system'));
        $this->assertFalse(Schema::hasColumn('beneficiarios', 'source_external_request_id'));
        $this->assertFalse(Schema::hasColumn('beneficiarios', 'curp_hash'));
        $this->assertFalse(Schema::hasColumn('beneficiarios', 'api_tj_sync_status'));
    }

    public function test_integration_migrations_can_be_rolled_back_without_dropping_core_tables(): void
    {
        $this->runMigrateFresh();

        foreach (array_reverse($this->integrationMigrationFiles) as $filename) {
            $exitCode = Artisan::call('migrate:rollback', [
                '--path' => base_path('database/migrations/'.$filename),
                '--realpath' => true,
                '--force' => true,
            ]);

            $this->assertSame(0, $exitCode, "Expected rollback to succeed for [{$filename}].");
        }

        foreach ($this->integrationTables as $table) {
            $this->assertFalse(Schema::hasTable($table), "Expected table [{$table}] to be absent after rollback.");
        }

        $this->assertTrue(Schema::hasTable('beneficiarios'));
        $this->assertTrue(Schema::hasTable('domicilios'));
        $this->assertTrue(Schema::hasTable('users'));
    }

    public function test_integration_migration_files_only_create_or_drop_integration_tables(): void
    {
        foreach ($this->integrationMigrationFiles as $filename) {
            $contents = file_get_contents(base_path('database/migrations/'.$filename));

            $this->assertIsString($contents);
            $this->assertStringContainsString("Schema::create('integration_", $contents);
            $this->assertStringNotContainsString('Schema::table(', $contents);
            $this->assertStringNotContainsString("Schema::table('beneficiarios'", $contents);
            $this->assertStringNotContainsString("Schema::table('domicilios'", $contents);
            $this->assertStringNotContainsString("Schema::table('users'", $contents);
            $this->assertStringContainsString('Schema::dropIfExists(', $contents);
        }
    }

    private function runMigrateFresh(): void
    {
        $exitCode = Artisan::call('migrate:fresh', [
            '--force' => true,
        ]);

        $this->assertSame(0, $exitCode, 'Expected migrate:fresh to finish successfully.');
    }
}
