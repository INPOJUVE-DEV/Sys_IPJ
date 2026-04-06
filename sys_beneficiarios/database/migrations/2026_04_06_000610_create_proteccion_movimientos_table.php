<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proteccion_movimientos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('proteccion_id');
            $table->string('tipo', 32)->index();
            $table->char('actor_uuid', 36);
            $table->char('from_usuario_uuid', 36)->nullable();
            $table->char('to_usuario_uuid', 36)->nullable();
            $table->uuid('beneficiario_id')->nullable();
            $table->json('metadata_json')->nullable();
            $table->timestamps();

            $table->foreign('proteccion_id')
                ->references('id')
                ->on('protecciones')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->foreign('actor_uuid')
                ->references('uuid')
                ->on('users')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
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

            $table->index(['proteccion_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proteccion_movimientos');
    }
};
