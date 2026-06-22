<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use function Pest\Laravel\postJson;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('logs in and out via sanctum', function () {
    $user = User::factory()->create([
        'email' => 'admin@example.com',
        'password' => Hash::make('password'),
    ]);

    $login = postJson('/api/auth/login', [
        'email' => 'admin@example.com',
        'password' => 'password',
    ]);

    $login->assertOk()
        ->assertJson([
            'token_type' => 'Bearer',
        ])
        ->assertJsonStructure(['token']);

    $token = $login->json('token');

    $logout = $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson('/api/auth/logout');

    $logout->assertNoContent();

    expect($user->fresh()->tokens)->toHaveCount(0);
});
