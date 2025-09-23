<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vol_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('beneficiario_id')
                ->constrained('beneficiarios')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->enum('payment_type', ['transferencia', 'tarjeta', 'deposito']);
            $table->date('payment_date');
            $table->string('receipt_ref')->nullable();
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamps();

            $table->index(['beneficiario_id', 'payment_date']);
            $table->index('payment_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vol_payments');
    }
};
