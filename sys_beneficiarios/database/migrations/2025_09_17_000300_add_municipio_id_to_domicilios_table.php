<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('domicilios', function (Blueprint $table) {
            if (!Schema::hasColumn('domicilios', 'municipio_id')) {
                $table->foreignId('municipio_id')->nullable()->after('colonia')
                    ->constrained('municipios')->cascadeOnUpdate()->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('domicilios', function (Blueprint $table) {
            if (Schema::hasColumn('domicilios', 'municipio_id')) {
                $table->dropConstrainedForeignId('municipio_id');
            }
        });
    }
};

