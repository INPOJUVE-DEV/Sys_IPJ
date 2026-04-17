<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('vale_movimientos');
        Schema::dropIfExists('vale_blocs');
    }

    public function down(): void
    {
        // Vales were removed from the domain; this migration is intentionally irreversible.
    }
};
