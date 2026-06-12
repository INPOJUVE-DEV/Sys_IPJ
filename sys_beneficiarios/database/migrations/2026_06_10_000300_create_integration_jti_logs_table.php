<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_jti_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('client_id')->nullable();
            $table->string('issuer');
            $table->string('jti');
            $table->timestamp('expires_at');
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('client_id')
                ->references('id')
                ->on('integration_clients')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->unique(['issuer', 'jti']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_jti_logs');
    }
};
