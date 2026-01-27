<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inscripciones', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('beneficiario_id');
            $table->unsignedBigInteger('programa_id');
            $table->string('periodo', 7);
            $table->string('estatus')->default('inscrito');
            $table->timestamp('fecha_renovacion')->nullable();
            $table->char('created_by', 36);
            $table->timestamps();

            $table->foreign('beneficiario_id')->references('id')->on('beneficiarios')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreign('programa_id')->references('id')->on('programas')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreign('created_by')->references('uuid')->on('users')->cascadeOnUpdate()->restrictOnDelete();
            $table->unique(['beneficiario_id', 'programa_id', 'periodo'], 'inscripciones_beneficiario_programa_periodo_unique');
            $table->index(['programa_id', 'periodo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inscripciones');
    }
};
