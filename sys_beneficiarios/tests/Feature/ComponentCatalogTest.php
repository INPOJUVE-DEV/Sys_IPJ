<?php

use App\Models\ComponentCatalog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    Role::firstOrCreate(['name' => 'admin']);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
});

it('permite al admin registrar o actualizar componentes', function () {
    $payload = [
        'key' => 'hero',
        'name' => 'Hero',
        'description' => 'Hero principal',
        'enabled' => true,
        'schema' => [
            'type' => 'object',
            'required' => ['title'],
            'properties' => [
                'title' => ['type' => 'string', 'max' => 120],
            ],
        ],
    ];

    $response = actingAs($this->admin)->postJson('/admin/components', $payload);
    $response->assertCreated()->assertJsonPath('data.key', 'hero');

    $index = actingAs($this->admin)->getJson('/admin/components');
    $index->assertOk()->assertJsonPath('data.0.key', 'hero');
});

it('excluye componentes deshabilitados del registro publico', function () {
    ComponentCatalog::updateOrCreate(
        ['key' => 'hero'],
        [
            'name' => 'Hero',
            'description' => 'Hero',
            'schema' => ['type' => 'object'],
            'enabled' => true,
        ]
    );

    ComponentCatalog::updateOrCreate(
        ['key' => 'legacy'],
        [
            'name' => 'Legacy',
            'description' => 'Legacy component',
            'schema' => ['type' => 'object'],
            'enabled' => false,
        ]
    );

    $response = getJson('/api/v1/components/registry');

    $response->assertOk();
    $keys = collect($response->json('data'))->pluck('key');
    expect($keys)->toContain('hero');
    expect($keys)->not->toContain('legacy');
});