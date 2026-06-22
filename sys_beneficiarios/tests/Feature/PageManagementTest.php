<?php

namespace Tests\Feature;

use App\Models\ComponentCatalog;
use App\Models\Page;
use App\Models\PageVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class PageManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    /**
     * @var array<string, mixed>
     */
    protected array $basePayload;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Role::firstOrCreate(['name' => 'admin']);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        ComponentCatalog::updateOrCreate(
            ['key' => 'hero'],
            [
                'name' => 'Hero',
                'description' => 'Hero section',
                'schema' => [
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
                ],
                'enabled' => true,
            ]
        );

        ComponentCatalog::updateOrCreate(
            ['key' => 'card_grid'],
            [
                'name' => 'Card Grid',
                'description' => 'Cards layout',
                'schema' => [
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
                ],
                'enabled' => true,
            ]
        );

        $this->basePayload = [
            'slug' => 'landing-hero',
            'title' => 'Landing principal',
            'layout_json' => [
                [
                    'type' => 'hero',
                    'props' => [
                        'title' => 'Bienvenido',
                        'subtitle' => 'Subtitulo destacado',
                        'cta' => [
                            'label' => 'Comenzar',
                            'url' => 'https://example.com/inicio',
                        ],
                        'background_image' => 'https://example.com/portada.jpg',
                    ],
                ],
                [
                    'type' => 'card_grid',
                    'props' => [
                        'columns' => 3,
                        'cards' => [
                            [
                                'title' => 'Card 1',
                                'body' => 'Contenido introductorio',
                                'url' => 'https://example.com/card-1',
                            ],
                        ],
                    ],
                ],
            ],
            'notes' => 'Borrador inicial',
        ];
    }

    public function test_crea_una_pagina_con_borrador(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/admin/pages', $this->basePayload);

        $response->assertCreated()->assertJsonPath('data.slug', 'landing-hero');

        $page = Page::where('slug', 'landing-hero')->first();
        $this->assertNotNull($page);
        $this->assertSame(1, $page->versions()->draft()->count());
    }

    public function test_rechaza_un_borrador_con_componente_inexistente(): void
    {
        $this->actingAs($this->admin)->postJson('/admin/pages', $this->basePayload);

        $response = $this->actingAs($this->admin)->putJson('/admin/pages/landing-hero/draft', [
            'title' => 'Layout invalido',
            'layout_json' => [
                ['type' => 'video', 'props' => []],
            ],
            'notes' => null,
        ]);

        $response->assertStatus(422);
        $errors = $response->json('errors');
        $this->assertSame("El componente 'video' no existe en el catalogo.", $errors['layout_json.0.type'][0]);
    }

    public function test_rechaza_un_borrador_con_props_invalidos_segun_schema(): void
    {
        $this->actingAs($this->admin)->postJson('/admin/pages', $this->basePayload);

        $response = $this->actingAs($this->admin)->putJson('/admin/pages/landing-hero/draft', [
            'title' => 'Layout invalido',
            'layout_json' => [
                [
                    'type' => 'hero',
                    'props' => [
                        'title' => 'Falta subtitulo',
                    ],
                ],
            ],
            'notes' => null,
        ]);

        $response->assertStatus(422);
        $errors = $response->json('errors');
        $this->assertSame("El campo 'subtitle' es obligatorio.", $errors['layout_json.0.props.subtitle'][0]);
    }

    public function test_rechaza_publicar_cuando_el_componente_esta_deshabilitado(): void
    {
        $this->actingAs($this->admin)->postJson('/admin/pages', $this->basePayload);
        ComponentCatalog::where('key', 'hero')->update(['enabled' => false]);

        $response = $this->actingAs($this->admin)->postJson('/admin/pages/landing-hero/publish');

        $response->assertStatus(422);
        $errors = $response->json('errors');
        $this->assertSame("El componente 'hero' esta deshabilitado.", $errors['layout_json.0.type'][0]);
    }

    public function test_publica_una_pagina_y_genera_nuevo_borrador(): void
    {
        $this->actingAs($this->admin)->postJson('/admin/pages', $this->basePayload);

        $publish = $this->actingAs($this->admin)->postJson('/admin/pages/landing-hero/publish');

        $publish->assertOk()
            ->assertJsonPath('published.status', PageVersion::STATUS_PUBLISHED)
            ->assertJsonPath('draft.status', PageVersion::STATUS_DRAFT);

        $page = Page::where('slug', 'landing-hero')->first();
        $this->assertNotNull($page?->publishedVersion);
        $this->assertSame(1, $page->versions()->draft()->count());
    }

    public function test_mantiene_inmutable_la_version_publicada_al_editar_el_nuevo_borrador(): void
    {
        $this->actingAs($this->admin)->postJson('/admin/pages', $this->basePayload);
        $this->actingAs($this->admin)->postJson('/admin/pages/landing-hero/publish');

        $page = Page::where('slug', 'landing-hero')->firstOrFail();
        $publishedLayout = $page->publishedVersion->layout_json;

        $update = $this->actingAs($this->admin)->putJson('/admin/pages/landing-hero/draft', [
            'title' => 'Landing v2',
            'layout_json' => [
                [
                    'type' => 'hero',
                    'props' => [
                        'title' => 'Nuevo titulo',
                        'subtitle' => 'Nueva bajada',
                        'cta' => [
                            'label' => 'Ir ahora',
                            'url' => 'https://example.com/cta',
                        ],
                    ],
                ],
            ],
            'notes' => 'Cambios pendientes',
        ]);

        $update->assertOk();

        $page->refresh();
        $this->assertSame($publishedLayout, $page->publishedVersion->layout_json);
        $this->assertSame('Landing v2', $page->currentDraft()->title);
    }

    public function test_realiza_rollback_a_una_version_previa(): void
    {
        $this->actingAs($this->admin)->postJson('/admin/pages', $this->basePayload);
        $this->actingAs($this->admin)->postJson('/admin/pages/landing-hero/publish');

        $this->actingAs($this->admin)->putJson('/admin/pages/landing-hero/draft', [
            'title' => 'Landing v2',
            'layout_json' => [
                [
                    'type' => 'hero',
                    'props' => [
                        'title' => 'Nuevo titulo',
                        'subtitle' => 'Nueva bajada',
                        'cta' => [
                            'label' => 'CTA',
                            'url' => 'https://example.com/cta',
                        ],
                    ],
                ],
            ],
            'notes' => 'Iteracion 2',
        ]);
        $this->actingAs($this->admin)->postJson('/admin/pages/landing-hero/publish');

        $rollback = $this->actingAs($this->admin)->postJson('/admin/pages/landing-hero/rollback', [
            'version' => 1,
        ]);

        $rollback->assertOk()
            ->assertJsonPath('published.version', 3);

        $page = Page::where('slug', 'landing-hero')->firstOrFail();
        $this->assertSame('Landing principal', $page->publishedVersion->title);
        $this->assertSame(4, $page->currentDraft()->version);
    }

    public function test_expone_la_version_publicada_con_etag_y_soporta_if_none_match(): void
    {
        $this->actingAs($this->admin)->postJson('/admin/pages', $this->basePayload);
        $this->actingAs($this->admin)->postJson('/admin/pages/landing-hero/publish');

        $first = $this->getJson('/api/pages/landing-hero');
        $first->assertOk()->assertHeader('ETag');

        $etag = $first->headers->get('ETag');

        $second = $this->getJson('/api/pages/landing-hero', [
            'If-None-Match' => $etag,
        ]);
        $second->assertStatus(304);
    }
}
