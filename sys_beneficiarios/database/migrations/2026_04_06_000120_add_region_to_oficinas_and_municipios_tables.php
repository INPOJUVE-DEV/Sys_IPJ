<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('oficinas', function (Blueprint $table) {
            if (! Schema::hasColumn('oficinas', 'region')) {
                $table->string('region', 80)->nullable()->after('tipo')->index();
            }
        });

        Schema::table('municipios', function (Blueprint $table) {
            if (! Schema::hasColumn('municipios', 'region')) {
                $table->string('region', 80)->nullable()->after('nombre')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('municipios', function (Blueprint $table) {
            if (Schema::hasColumn('municipios', 'region')) {
                $table->dropColumn('region');
            }
        });

        Schema::table('oficinas', function (Blueprint $table) {
            if (Schema::hasColumn('oficinas', 'region')) {
                $table->dropColumn('region');
            }
        });
    }
};
