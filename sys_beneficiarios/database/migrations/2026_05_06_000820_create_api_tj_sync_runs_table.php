<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_tj_sync_runs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('sync_id')->unique();
            $table->char('executed_by', 36)->nullable();
            $table->string('role', 64)->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('request_count')->default(0);
            $table->unsignedInteger('success_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->unsignedSmallInteger('api_status_code')->nullable();
            $table->longText('api_response_body')->nullable();
            $table->string('status', 32)->index();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->foreign('executed_by')
                ->references('uuid')
                ->on('users')
                ->cascadeOnUpdate()
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_tj_sync_runs');
    }
};
