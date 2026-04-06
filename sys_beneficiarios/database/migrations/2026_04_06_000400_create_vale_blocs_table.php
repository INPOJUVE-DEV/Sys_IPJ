<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vale_blocs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('folio_inicio');
            $table->unsignedBigInteger('folio_fin');
            $table->unsignedInteger('cantidad')->default(1000);
            $table->string('estatus', 32)->index();
            $table->foreignId('oficina_id')
                ->nullable()
                ->constrained('oficinas')
                ->cascadeOnUpdate()
                ->nullOnDelete();
            $table->char('usuario_uuid', 36)->nullable();
            $table->text('observaciones')->nullable();
            $table->timestamps();

            $table->foreign('usuario_uuid')
                ->references('uuid')
                ->on('users')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->unique(['folio_inicio', 'folio_fin']);
            $table->index(['oficina_id', 'estatus']);
            $table->index(['usuario_uuid', 'estatus']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vale_blocs');
    }
};
