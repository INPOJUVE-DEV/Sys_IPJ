<?php

namespace Tests\Feature;

use App\Models\Programa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProgramasTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RoleSeeder::class);
    }

    public function test_admin_can_create_programa(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $payload = [
            'nombre' => 'Clases de Guitarra',
            'tipo_periodo' => 'mensual',
            'renovable' => '1',
            'activo' => '1',
        ];

        $this->actingAs($admin)
            ->post(route('programas.store'), $payload)
            ->assertRedirect(route('programas.index'));

        $this->assertDatabaseHas('programas', [
            'nombre' => 'Clases de Guitarra',
            'slug' => 'clases-de-guitarra',
            'tipo_periodo' => 'mensual',
            'renovable' => 1,
            'activo' => 1,
        ]);
    }

    public function test_capturista_cannot_access_programas(): void
    {
        $user = User::factory()->create();
        $user->assignRole('capturista');

        $this->actingAs($user)->get(route('programas.index'))->assertForbidden();
    }
}
