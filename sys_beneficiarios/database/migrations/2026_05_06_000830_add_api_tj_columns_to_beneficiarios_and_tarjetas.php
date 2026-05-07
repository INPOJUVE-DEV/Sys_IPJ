<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('beneficiarios', function (Blueprint $table) {
            if (! Schema::hasColumn('beneficiarios', 'source_system')) {
                $table->string('source_system', 32)->nullable()->after('created_by');
                $table->index('source_system');
            }

            if (! Schema::hasColumn('beneficiarios', 'source_external_request_id')) {
                $table->string('source_external_request_id')->nullable()->after('source_system');
                $table->index('source_external_request_id');
            }
        });

        Schema::table('tarjetas', function (Blueprint $table) {
            if (! Schema::hasColumn('tarjetas', 'source_system')) {
                $table->string('source_system', 32)->nullable()->after('beneficiario_id');
                $table->index('source_system');
            }

            if (! Schema::hasColumn('tarjetas', 'is_digital')) {
                $table->boolean('is_digital')->default(false)->after('source_system');
                $table->index('is_digital');
            }
        });
    }

    public function down(): void
    {
        Schema::table('beneficiarios', function (Blueprint $table) {
            if (Schema::hasColumn('beneficiarios', 'source_external_request_id')) {
                $table->dropIndex(['source_external_request_id']);
                $table->dropColumn('source_external_request_id');
            }

            if (Schema::hasColumn('beneficiarios', 'source_system')) {
                $table->dropIndex(['source_system']);
                $table->dropColumn('source_system');
            }
        });

        Schema::table('tarjetas', function (Blueprint $table) {
            if (Schema::hasColumn('tarjetas', 'is_digital')) {
                $table->dropIndex(['is_digital']);
                $table->dropColumn('is_digital');
            }

            if (Schema::hasColumn('tarjetas', 'source_system')) {
                $table->dropIndex(['source_system']);
                $table->dropColumn('source_system');
            }
        });
    }
};
