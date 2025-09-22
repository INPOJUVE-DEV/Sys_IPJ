<?php

use App\Models\ComponentCatalog;
use App\Models\Page;
use App\Models\PageVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\putJson;

uses(RefreshDatabase::class);

beforeEach(function () {
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
});

it('crea una pagina con borrador', function () {
    $response = actingAs($this->admin)->postJson('/admin/pages', $this->basePayload);

    $response->assertCreated()->assertJsonPath('data.slug', 'landing-hero');

    $page = Page::where('slug', 'landing-hero')->first();
    expect($page)->not->toBeNull();
    expect($page->versions()->draft()->count())->toBe(1);
});

it('rechaza un borrador con componente inexistente', function () {
    actingAs($this->admin)->postJson('/admin/pages', $this->basePayload);

    $response = actingAs($this->admin)->putJson('/admin/pages/landing-hero/draft', [
        'title' => 'Layout invalido',
        'layout_json' => [
            ['type' => 'video', 'props' => []],
        ],
        'notes' => null,
    ]);

    $response->assertStatus(422);
    $errors = $response->json('errors');
    expect($errors['layout_json.0.type'][0])->toBe("El componente 'video' no existe en el catálogo.");
});

it('rechaza un borrador con props invalidos segun schema', function () {
    actingAs($this->admin)->postJson('/admin/pages', $this->basePayload);

    $response = actingAs($this->admin)->putJson('/admin/pages/landing-hero/draft', [
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
    expect($errors['layout_json.0.props.subtitle'][0])->toBe("El campo 'subtitle' es obligatorio.");
});

it('rechaza publicar cuando el componente esta deshabilitado', function () {
    actingAs($this->admin)->postJson('/admin/pages', $this->basePayload);
    ComponentCatalog::where('key', 'hero')->update(['enabled' => false]);

    $response = actingAs($this->admin)->postJson('/admin/pages/landing-hero/publish');

    $response->assertStatus(422);
    $errors = $response->json('errors');
    expect($errors['layout_json.0.type'][0])->toBe("El componente 'hero' está deshabilitado.");
});

it('publica una pagina y genera nuevo borrador', function () {
    actingAs($this->admin)->postJson('/admin/pages', $this->basePayload);

    $publish = actingAs($this->admin)->postJson('/admin/pages/landing-hero/publish');

    $publish->assertOk()
        ->assertJsonPath('published.status', PageVersion::STATUS_PUBLISHED)
        ->assertJsonPath('draft.status', PageVersion::STATUS_DRAFT);

    $page = Page::where('slug', 'landing-hero')->first();
    expect($page->publishedVersion)->not->toBeNull();
    expect($page->versions()->draft()->count())->toBe(1);
});

it('mantiene inmutable la version publicada al editar el nuevo borrador', function () {
    actingAs($this->admin)->postJson('/admin/pages', $this->basePayload);
    actingAs($this->admin)->postJson('/admin/pages/landing-hero/publish');
    $page = Page::where('slug', 'landing-hero')->first();
    $publishedLayout = $page->publishedVersion->layout_json;

    $update = actingAs($this->admin)->putJson('/admin/pages/landing-hero/draft', [
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
    expect($page->publishedVersion->layout_json)->toBe($publishedLayout);
    expect($page->currentDraft()->title)->toBe('Landing v2');
});

it('realiza rollback a una version previa', function () {
    actingAs($this->admin)->postJson('/admin/pages', $this->basePayload);
    actingAs($this->admin)->postJson('/admin/pages/landing-hero/publish');

    actingAs($this->admin)->putJson('/admin/pages/landing-hero/draft', [
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
    actingAs($this->admin)->postJson('/admin/pages/landing-hero/publish');

    $rollback = actingAs($this->admin)->postJson('/admin/pages/landing-hero/rollback', [
        'version' => 1,
    ]);

    $rollback->assertOk()
        ->assertJsonPath('published.version', 3);

    $page = Page::where('slug', 'landing-hero')->first();
    expect($page->publishedVersion->title)->toBe('Landing principal');
    expect($page->currentDraft()->version)->toBe(4);
});

it('expone la version publicada con ETag y soporta If-None-Match', function () {
    actingAs($this->admin)->postJson('/admin/pages', $this->basePayload);
    actingAs($this->admin)->postJson('/admin/pages/landing-hero/publish');

    $first = getJson('/api/v1/pages/landing-hero');
    $first->assertOk()->assertHeader('ETag');

    $etag = $first->headers->get('ETag');

    $second = getJson('/api/v1/pages/landing-hero', ['If-None-Match' => $etag]);
    $second->assertStatus(304);
});
