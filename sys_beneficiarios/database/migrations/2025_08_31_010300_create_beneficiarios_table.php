<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('beneficiarios', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('folio_tarjeta')->unique();
            $table->string('nombre');
            $table->string('apellido_paterno');
            $table->string('apellido_materno');
            $table->string('curp')->unique();
            $table->date('fecha_nacimiento');
            $table->unsignedTinyInteger('edad');
            $table->enum('sexo', ['M','F','X'])->nullable();
            $table->boolean('discapacidad')->default(false);
            $table->string('id_ine')->nullable();
            $table->string('telefono', 10);
            $table->foreignId('municipio_id')->nullable()->constrained('municipios')->cascadeOnUpdate()->nullOnDelete();
            $table->string('seccional');
            $table->string('distrito_local');
            $table->string('distrito_federal');
            $table->char('created_by', 36);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('created_by')->references('uuid')->on('users')->cascadeOnUpdate()->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('beneficiarios');
    }
};

