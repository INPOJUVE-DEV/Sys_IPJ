<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_sync_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('sync_run_id');
            $table->uuid('beneficiario_id')->nullable();
            $table->string('payload_hash', 64);
            $table->string('status', 32)->index();
            $table->unsignedSmallInteger('response_code')->nullable();
            $table->json('response_body')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->foreign('sync_run_id')
                ->references('id')
                ->on('integration_sync_runs')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->foreign('beneficiario_id')
                ->references('id')
                ->on('beneficiarios')
                ->cascadeOnUpdate()
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_sync_items');
    }
};
