<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('beneficiarios', function (Blueprint $table) {
            if (Schema::hasColumn('beneficiarios', 'is_draft')) {
                $table->dropColumn('is_draft');
            }
        });
    }

    public function down(): void
    {
        Schema::table('beneficiarios', function (Blueprint $table) {
            if (! Schema::hasColumn('beneficiarios', 'is_draft')) {
                $table->boolean('is_draft')->default(true);
            }
        });
    }
};
