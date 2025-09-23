<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vol_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')
                ->constrained('vol_groups')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->foreignUuid('beneficiario_id')
                ->constrained('beneficiarios')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->enum('status', ['inscrito', 'baja'])->default('inscrito');
            $table->dateTime('enrolled_at');
            $table->date('enrolled_on')->storedAs('DATE(enrolled_at)');
            $table->dateTime('unenrolled_at')->nullable();
            $table->text('reason')->nullable();
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamps();

            $table->unique(['group_id', 'beneficiario_id']);
            $table->index(['beneficiario_id', 'enrolled_on'], 'vol_enrollments_beneficiario_enrolled_on_idx');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vol_enrollments');
    }
};
