<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('protecciones', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('tipo');
            $table->string('numero_inventario')->unique();
            $table->string('estatus', 32)->index();
            $table->char('usuario_uuid', 36);
            $table->uuid('beneficiario_id')->nullable();
            $table->timestamp('prestada_at')->nullable();
            $table->text('observaciones')->nullable();
            $table->timestamps();

            $table->foreign('usuario_uuid')
                ->references('uuid')
                ->on('users')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreign('beneficiario_id')
                ->references('id')
                ->on('beneficiarios')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->index(['usuario_uuid', 'estatus']);
            $table->index(['beneficiario_id', 'estatus']);
            $table->index(['tipo', 'estatus']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('protecciones');
    }
};
