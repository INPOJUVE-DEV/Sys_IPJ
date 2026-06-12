<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_inbound_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('source_system', 64)->index();
            $table->string('external_request_id');
            $table->string('operation', 64)->index();
            $table->string('request_hash', 64);
            $table->longText('request_payload_encrypted')->nullable();
            $table->string('status', 32)->index();
            $table->unsignedSmallInteger('response_code')->nullable();
            $table->json('response_body')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('received_at');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['source_system', 'external_request_id'],
                'int_inbound_req_source_ext_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_inbound_requests');
    }
};
