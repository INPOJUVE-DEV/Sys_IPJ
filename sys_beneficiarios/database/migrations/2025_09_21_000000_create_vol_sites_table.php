<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vol_sites', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('state', 120);
            $table->string('city', 120);
            $table->string('address');
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vol_sites');
    }
};