<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('beneficiarios', function (Blueprint $table) {
            if (! Schema::hasColumn('beneficiarios', 'tarjeta_id')) {
                $table->uuid('tarjeta_id')->nullable()->after('folio_tarjeta');
                $table->foreign('tarjeta_id')
                    ->references('id')
                    ->on('tarjetas')
                    ->cascadeOnUpdate()
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('beneficiarios', function (Blueprint $table) {
            if (Schema::hasColumn('beneficiarios', 'tarjeta_id')) {
                $table->dropForeign(['tarjeta_id']);
                $table->dropColumn('tarjeta_id');
            }
        });
    }
};
