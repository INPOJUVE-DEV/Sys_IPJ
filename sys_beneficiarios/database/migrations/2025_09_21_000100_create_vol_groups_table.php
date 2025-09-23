<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vol_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_id')
                ->constrained('programs')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreignId('site_id')
                ->constrained('vol_sites')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->string('code')->unique();
            $table->string('name');
            $table->enum('type', ['semanal', 'sabatino']);
            $table->enum('schedule_template', ['lmv', 'mj', 'sab']);
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->unsignedInteger('capacity')->default(12);
            $table->enum('state', ['borrador', 'publicado', 'cerrado'])->default('borrador');
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->foreignId('updated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['program_id', 'site_id']);
            $table->index('state');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vol_groups');
    }
};
