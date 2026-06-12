<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_client_keys', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('client_id');
            $table->string('kid');
            $table->text('public_key');
            $table->string('status', 32)->index();
            $table->timestamp('valid_from')->nullable();
            $table->timestamp('valid_until')->nullable();
            $table->timestamps();

            $table->foreign('client_id')
                ->references('id')
                ->on('integration_clients')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->unique(['client_id', 'kid']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_client_keys');
    }
};
