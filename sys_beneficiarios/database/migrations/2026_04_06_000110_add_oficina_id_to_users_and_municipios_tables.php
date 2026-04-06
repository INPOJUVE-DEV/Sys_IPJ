<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'oficina_id')) {
                $table->foreignId('oficina_id')
                    ->nullable()
                    ->after('uuid')
                    ->constrained('oficinas')
                    ->cascadeOnUpdate()
                    ->nullOnDelete();
            }
        });

        Schema::table('municipios', function (Blueprint $table) {
            if (! Schema::hasColumn('municipios', 'oficina_id')) {
                $table->foreignId('oficina_id')
                    ->nullable()
                    ->after('nombre')
                    ->constrained('oficinas')
                    ->cascadeOnUpdate()
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('municipios', function (Blueprint $table) {
            if (Schema::hasColumn('municipios', 'oficina_id')) {
                $table->dropConstrainedForeignId('oficina_id');
            }
        });

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'oficina_id')) {
                $table->dropConstrainedForeignId('oficina_id');
            }
        });
    }
};
