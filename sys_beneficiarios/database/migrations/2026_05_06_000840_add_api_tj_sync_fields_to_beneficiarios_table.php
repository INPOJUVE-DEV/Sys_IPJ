<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('beneficiarios', function (Blueprint $table) {
            if (! Schema::hasColumn('beneficiarios', 'curp_hash')) {
                $table->string('curp_hash', 64)->nullable()->after('curp');
                $table->index('curp_hash');
            }

            if (! Schema::hasColumn('beneficiarios', 'email')) {
                $table->string('email')->nullable()->after('telefono');
            }

            if (! Schema::hasColumn('beneficiarios', 'status')) {
                $table->string('status', 32)->default('active')->after('source_external_request_id');
                $table->index('status');
            }

            if (! Schema::hasColumn('beneficiarios', 'api_tj_sync_status')) {
                $table->string('api_tj_sync_status', 32)->default('pending_data')->after('status');
                $table->index('api_tj_sync_status');
            }

            if (! Schema::hasColumn('beneficiarios', 'api_tj_sync_attempts')) {
                $table->unsignedInteger('api_tj_sync_attempts')->default(0)->after('api_tj_sync_status');
            }

            if (! Schema::hasColumn('beneficiarios', 'api_tj_last_sync_error')) {
                $table->text('api_tj_last_sync_error')->nullable()->after('api_tj_sync_attempts');
            }

            if (! Schema::hasColumn('beneficiarios', 'api_tj_last_synced_at')) {
                $table->timestamp('api_tj_last_synced_at')->nullable()->after('api_tj_last_sync_error');
            }
        });
    }

    public function down(): void
    {
        Schema::table('beneficiarios', function (Blueprint $table) {
            if (Schema::hasColumn('beneficiarios', 'api_tj_last_synced_at')) {
                $table->dropColumn('api_tj_last_synced_at');
            }

            if (Schema::hasColumn('beneficiarios', 'api_tj_last_sync_error')) {
                $table->dropColumn('api_tj_last_sync_error');
            }

            if (Schema::hasColumn('beneficiarios', 'api_tj_sync_attempts')) {
                $table->dropColumn('api_tj_sync_attempts');
            }

            if (Schema::hasColumn('beneficiarios', 'api_tj_sync_status')) {
                $table->dropIndex(['api_tj_sync_status']);
                $table->dropColumn('api_tj_sync_status');
            }

            if (Schema::hasColumn('beneficiarios', 'status')) {
                $table->dropIndex(['status']);
                $table->dropColumn('status');
            }

            if (Schema::hasColumn('beneficiarios', 'email')) {
                $table->dropColumn('email');
            }

            if (Schema::hasColumn('beneficiarios', 'curp_hash')) {
                $table->dropIndex(['curp_hash']);
                $table->dropColumn('curp_hash');
            }
        });
    }
};
