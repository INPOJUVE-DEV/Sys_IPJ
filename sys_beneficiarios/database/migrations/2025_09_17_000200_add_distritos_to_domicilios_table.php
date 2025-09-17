<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('domicilios', function (Blueprint $table) {
            if (!Schema::hasColumn('domicilios', 'distrito_local')) {
                $table->string('distrito_local')->nullable()->after('seccional');
            }
            if (!Schema::hasColumn('domicilios', 'distrito_federal')) {
                $table->string('distrito_federal')->nullable()->after('distrito_local');
            }
        });
    }

    public function down(): void
    {
        Schema::table('domicilios', function (Blueprint $table) {
            if (Schema::hasColumn('domicilios', 'distrito_federal')) {
                $table->dropColumn('distrito_federal');
            }
            if (Schema::hasColumn('domicilios', 'distrito_local')) {
                $table->dropColumn('distrito_local');
            }
        });
    }
};

