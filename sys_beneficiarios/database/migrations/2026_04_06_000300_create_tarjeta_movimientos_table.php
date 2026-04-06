<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tarjeta_movimientos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tarjeta_id');
            $table->string('tipo', 32)->index();
            $table->foreignId('from_oficina_id')
                ->nullable()
                ->constrained('oficinas')
                ->cascadeOnUpdate()
                ->nullOnDelete();
            $table->foreignId('to_oficina_id')
                ->nullable()
                ->constrained('oficinas')
                ->cascadeOnUpdate()
                ->nullOnDelete();
            $table->char('from_usuario_uuid', 36)->nullable();
            $table->char('to_usuario_uuid', 36)->nullable();
            $table->uuid('beneficiario_id')->nullable();
            $table->char('actor_uuid', 36);
            $table->json('metadata_json')->nullable();
            $table->timestamps();

            $table->foreign('tarjeta_id')
                ->references('id')
                ->on('tarjetas')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->foreign('from_usuario_uuid')
                ->references('uuid')
                ->on('users')
                ->cascadeOnUpdate()
                ->nullOnDelete();
            $table->foreign('to_usuario_uuid')
                ->references('uuid')
                ->on('users')
                ->cascadeOnUpdate()
                ->nullOnDelete();
            $table->foreign('beneficiario_id')
                ->references('id')
                ->on('beneficiarios')
                ->cascadeOnUpdate()
                ->nullOnDelete();
            $table->foreign('actor_uuid')
                ->references('uuid')
                ->on('users')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->index(['tarjeta_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tarjeta_movimientos');
    }
};
