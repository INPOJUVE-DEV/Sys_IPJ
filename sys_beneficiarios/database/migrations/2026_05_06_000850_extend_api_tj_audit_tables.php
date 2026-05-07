<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('api_tj_inbound_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('api_tj_inbound_requests', 'total_count')) {
                $table->unsignedInteger('total_count')->default(0)->after('payload_json');
            }

            if (! Schema::hasColumn('api_tj_inbound_requests', 'accepted_count')) {
                $table->unsignedInteger('accepted_count')->default(0)->after('total_count');
            }

            if (! Schema::hasColumn('api_tj_inbound_requests', 'rejected_count')) {
                $table->unsignedInteger('rejected_count')->default(0)->after('accepted_count');
            }

            if (! Schema::hasColumn('api_tj_inbound_requests', 'result_json')) {
                $table->longText('result_json')->nullable()->after('rejected_count');
            }
        });

        Schema::table('api_tj_sync_runs', function (Blueprint $table) {
            if (! Schema::hasColumn('api_tj_sync_runs', 'request_payload_json')) {
                $table->longText('request_payload_json')->nullable()->after('request_count');
            }
        });
    }

    public function down(): void
    {
        Schema::table('api_tj_sync_runs', function (Blueprint $table) {
            if (Schema::hasColumn('api_tj_sync_runs', 'request_payload_json')) {
                $table->dropColumn('request_payload_json');
            }
        });

        Schema::table('api_tj_inbound_requests', function (Blueprint $table) {
            if (Schema::hasColumn('api_tj_inbound_requests', 'result_json')) {
                $table->dropColumn('result_json');
            }

            if (Schema::hasColumn('api_tj_inbound_requests', 'rejected_count')) {
                $table->dropColumn('rejected_count');
            }

            if (Schema::hasColumn('api_tj_inbound_requests', 'accepted_count')) {
                $table->dropColumn('accepted_count');
            }

            if (Schema::hasColumn('api_tj_inbound_requests', 'total_count')) {
                $table->dropColumn('total_count');
            }
        });
    }
};
