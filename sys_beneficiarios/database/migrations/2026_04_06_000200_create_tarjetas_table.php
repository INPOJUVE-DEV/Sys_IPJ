<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tarjetas', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('folio')->unique();
            $table->string('estatus', 32)->index();
            $table->foreignId('oficina_id')
                ->nullable()
                ->constrained('oficinas')
                ->cascadeOnUpdate()
                ->nullOnDelete();
            $table->char('usuario_uuid', 36)->nullable();
            $table->foreignId('municipio_id')
                ->nullable()
                ->constrained('municipios')
                ->cascadeOnUpdate()
                ->nullOnDelete();
            $table->uuid('beneficiario_id')->nullable();
            $table->text('observaciones')->nullable();
            $table->timestamps();

            $table->foreign('usuario_uuid')
                ->references('uuid')
                ->on('users')
                ->cascadeOnUpdate()
                ->nullOnDelete();
            $table->foreign('beneficiario_id')
                ->references('id')
                ->on('beneficiarios')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->index(['oficina_id', 'estatus']);
            $table->index(['usuario_uuid', 'estatus']);
            $table->index(['municipio_id', 'estatus']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tarjetas');
    }
};
