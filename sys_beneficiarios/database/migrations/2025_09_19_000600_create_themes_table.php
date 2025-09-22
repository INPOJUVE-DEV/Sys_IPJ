<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('themes', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->json('tokens');
            $table->boolean('is_active')->default(false)->index();
            $table->timestamps();
        });

        DB::table('themes')->insert([
            'name' => 'Default Theme',
            'tokens' => json_encode([
                'colors' => [
                    'primary' => '#1E40AF',
                    'secondary' => '#0EA5E9',
                    'background' => '#FFFFFF',
                    'surface' => '#F1F5F9',
                    'text' => '#0F172A',
                ],
                'typography' => [
                    'font_family' => '"Inter", sans-serif',
                    'line_height' => 1.5,
                    'scale' => [
                        'xs' => '0.75rem',
                        'sm' => '0.875rem',
                        'base' => '1rem',
                        'lg' => '1.125rem',
                        'xl' => '1.25rem',
                    ],
                ],
                'spacing' => [
                    'xs' => 4,
                    'sm' => 8,
                    'md' => 16,
                    'lg' => 24,
                    'xl' => 32,
                ],
                'radius' => [
                    'sm' => 4,
                    'md' => 8,
                    'lg' => 16,
                ],
            ]),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('themes');
    }
};
