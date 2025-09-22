<?php

use App\Models\Theme;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;
use function Pest\Laravel\putJson;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    Role::firstOrCreate(['name' => 'admin']);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');

    Theme::query()->delete();

    Theme::create([
        'name' => 'Initial Theme',
        'tokens' => [
            'colors' => [
                'primary' => '#000000',
                'secondary' => '#111111',
                'background' => '#FFFFFF',
                'surface' => '#F8FAFC',
                'text' => '#0F172A',
            ],
            'typography' => [
                'font_family' => 'Inter',
                'line_height' => 1.5,
                'scale' => [
                    'base' => '1rem',
                ],
            ],
            'spacing' => [
                'md' => 16,
            ],
        ],
        'is_active' => true,
    ]);
});

it('expone el tema activo en el endpoint público con ETag y cache', function () {
    Cache::flush();

    $first = getJson('/api/v1/themes/current');
    $first->assertOk()->assertJsonPath('data.name', 'Initial Theme')->assertHeader('ETag');
    $etag = $first->headers->get('ETag');

    $second = getJson('/api/v1/themes/current', ['If-None-Match' => $etag]);
    $second->assertStatus(304);
});

it('permite actualizar tokens del tema activo y limpia cache', function () {
    Cache::flush();

    $initial = getJson('/api/v1/themes/current');
    $initial->assertOk()->assertHeader('ETag');
    $oldEtag = $initial->headers->get('ETag');

    $payload = [
        'name' => 'Nuevo tema',
        'tokens' => [
            'colors' => [
                'primary' => '#1E40AF',
                'secondary' => '#0EA5E9',
                'background' => '#FFFFFF',
                'surface' => '#F1F5F9',
                'text' => '#0F172A',
            ],
            'typography' => [
                'font_family' => 'Inter',
                'line_height' => 1.5,
                'scale' => [
                    'base' => '1rem',
                ],
            ],
            'spacing' => [
                'md' => 20,
            ],
        ],
    ];

    $response = actingAs($this->admin)->putJson('/admin/themes/current', $payload);

    $response->assertOk()->assertJsonPath('data.name', 'Nuevo tema');
    expect(Cache::has('theme.current'))->toBeTrue();
    expect(Cache::has('theme.current.payload'))->toBeFalse();

    $public = getJson('/api/v1/themes/current');
    $public->assertOk()->assertJsonPath('data.name', 'Nuevo tema')->assertHeader('ETag');
    expect($public->headers->get('ETag'))->not->toBe($oldEtag);
});

it('rechaza tokens inválidos', function () {
    $payload = [
        'name' => 'Tema inválido',
        'tokens' => [
            'colors' => [
                'primary' => 'not-hex',
            ],
            'typography' => [
                'font_family' => 'Inter',
                'line_height' => 3,
                'scale' => [
                    'base' => '1rem',
                ],
            ],
            'spacing' => [
                'md' => -10,
            ],
        ],
    ];

    $response = actingAs($this->admin)->putJson('/admin/themes/current', $payload);
    $response->assertStatus(422)
        ->assertJsonPath('errors."tokens.colors.secondary".0', 'The tokens.colors.secondary field is required.');
});