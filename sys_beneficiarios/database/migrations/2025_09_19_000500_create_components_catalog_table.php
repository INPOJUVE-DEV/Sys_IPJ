<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('components_catalog', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->string('description')->nullable();
            $table->json('schema');
            $table->boolean('enabled')->default(true);
            $table->timestamps();
        });

        DB::table('components_catalog')->insert([
            [
                'key' => 'hero',
                'name' => 'Hero',
                'description' => 'Seccion hero con titulo, subtitulo y CTA opcional.',
                'schema' => json_encode([
                    'type' => 'object',
                    'required' => ['title', 'subtitle'],
                    'properties' => [
                        'title' => ['type' => 'string', 'max' => 120],
                        'subtitle' => ['type' => 'string', 'max' => 255],
                        'cta' => [
                            'type' => 'object',
                            'nullable' => true,
                            'required' => ['label', 'url'],
                            'properties' => [
                                'label' => ['type' => 'string', 'max' => 60],
                                'url' => ['type' => 'string', 'format' => 'url'],
                            ],
                        ],
                        'background_image' => ['type' => 'string', 'nullable' => true, 'format' => 'url'],
                    ],
                ]),
                'enabled' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'card_grid',
                'name' => 'Card Grid',
                'description' => 'Rejilla de tarjetas con titulo, descripcion y enlaces.',
                'schema' => json_encode([
                    'type' => 'object',
                    'required' => ['cards'],
                    'properties' => [
                        'columns' => ['type' => 'integer', 'min' => 1, 'max' => 4, 'nullable' => true],
                        'cards' => [
                            'type' => 'array',
                            'min' => 1,
                            'items' => [
                                'type' => 'object',
                                'required' => ['title', 'body'],
                                'properties' => [
                                    'title' => ['type' => 'string', 'max' => 120],
                                    'body' => ['type' => 'string', 'max' => 500],
                                    'url' => ['type' => 'string', 'nullable' => true, 'format' => 'url'],
                                ],
                            ],
                        ],
                    ],
                ]),
                'enabled' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('components_catalog');
    }
};

