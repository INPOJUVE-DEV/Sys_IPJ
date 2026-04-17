<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('eventos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evento_tipo_id')
                ->constrained('evento_tipos')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreignId('municipio_id')
                ->constrained('municipios')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreignId('oficina_id')
                ->nullable()
                ->constrained('oficinas')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->char('created_by', 36);
            $table->text('descripcion');
            $table->string('lugar');
            $table->string('rol_participacion', 24);
            $table->unsignedInteger('total_asistentes');
            $table->string('evidencia_url', 2048)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('created_by')
                ->references('uuid')
                ->on('users')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->index(['created_by', 'created_at']);
            $table->index(['evento_tipo_id', 'municipio_id']);
            $table->index('rol_participacion');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('eventos');
    }
};
