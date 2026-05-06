<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_tj_inbound_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('external_request_id')->unique();
            $table->string('source')->default('api_tj');
            $table->string('curp_masked', 32)->nullable();
            $table->uuid('beneficiario_id')->nullable();
            $table->string('status', 32)->index();
            $table->string('request_hash', 64)->nullable()->index();
            $table->unsignedSmallInteger('response_code')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->string('created_by_system', 64)->default('api_tj');
            $table->longText('payload_json')->nullable();
            $table->timestamps();

            $table->foreign('beneficiario_id')
                ->references('id')
                ->on('beneficiarios')
                ->cascadeOnUpdate()
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_tj_inbound_requests');
    }
};
