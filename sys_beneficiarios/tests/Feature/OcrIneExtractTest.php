<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OcrIneExtractTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RoleSeeder::class);

        // Set up OCR service config for testing
        config([
            'services.ocr_ine' => [
                'url' => 'http://ocr-test.local',
                'api_key' => 'test-key',
                'timeout' => 10,
            ],
        ]);
    }

    /** Create a fake JPEG file without requiring GD extension. */
    protected function fakeJpeg(string $name = 'photo.jpg', int $sizeKb = 100): UploadedFile
    {
        return UploadedFile::fake()->create($name, $sizeKb, 'image/jpeg');
    }

    protected function ocrSuccessResponse(): array
    {
        return [
            'model_id' => 'MODEL_QRHD_2019_PRESENT',
            'beneficiarios' => [
                'nombre' => ['value' => 'JUAN', 'confidence' => 0.91],
                'apellido_paterno' => ['value' => 'PEREZ', 'confidence' => 0.89],
                'apellido_materno' => ['value' => 'LOPEZ', 'confidence' => 0.87],
                'curp' => ['value' => 'PELJ000101HDFRPNA1', 'confidence' => 0.95],
                'fecha_nacimiento' => ['value' => '2000-01-01', 'confidence' => 0.95],
                'sexo' => ['value' => 'M', 'confidence' => 0.95],
                'id_ine' => ['value' => 'ABC123456789012345', 'confidence' => 0.84],
            ],
            'domicilio' => [
                'calle' => ['value' => 'AV REFORMA 100', 'confidence' => 0.72],
                'colonia' => ['value' => 'CENTRO', 'confidence' => 0.70],
                'codigo_postal' => ['value' => '06600', 'confidence' => 0.80],
                'seccional' => ['value' => '0234', 'confidence' => 0.93],
            ],
            'quality' => [
                'front' => ['quality_grade' => 'good'],
                'back' => ['quality_grade' => 'good'],
            ],
            'warnings' => [],
            'processing_ms' => 5200,
        ];
    }

    public function test_requires_authentication(): void
    {
        $response = $this->postJson('/api/ocr/ine/extract');
        $response->assertStatus(401);
    }

    public function test_requires_both_images(): void
    {
        $user = User::factory()->create();
        $user->assignRole('capturista');

        // No images
        $this->actingAs($user)
            ->postJson('/api/ocr/ine/extract')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['front_image', 'back_image']);

        // Only front
        $this->actingAs($user)
            ->postJson('/api/ocr/ine/extract', [
                'front_image' => $this->fakeJpeg('front.jpg'),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['back_image']);

        // Only back
        $this->actingAs($user)
            ->postJson('/api/ocr/ine/extract', [
                'back_image' => $this->fakeJpeg('back.jpg'),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['front_image']);
    }

    public function test_rejects_non_image_files(): void
    {
        $user = User::factory()->create();
        $user->assignRole('capturista');

        $this->actingAs($user)
            ->postJson('/api/ocr/ine/extract', [
                'front_image' => UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf'),
                'back_image' => $this->fakeJpeg('back.jpg'),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['front_image']);
    }

    public function test_rejects_oversized_images(): void
    {
        $user = User::factory()->create();
        $user->assignRole('capturista');

        $this->actingAs($user)
            ->postJson('/api/ocr/ine/extract', [
                'front_image' => $this->fakeJpeg('front.jpg', 6000),
                'back_image' => $this->fakeJpeg('back.jpg'),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['front_image']);
    }

    public function test_successful_ocr_extraction(): void
    {
        Http::fake([
            '*' => Http::response($this->ocrSuccessResponse(), 200),
        ]);

        $user = User::factory()->create();
        $user->assignRole('capturista');

        $response = $this->actingAs($user)
            ->postJson('/api/ocr/ine/extract', [
                'front_image' => $this->fakeJpeg('front.jpg'),
                'back_image' => $this->fakeJpeg('back.jpg'),
            ]);

        $response->assertOk()
            ->assertJsonPath('beneficiarios.nombre.value', 'JUAN')
            ->assertJsonPath('beneficiarios.curp.value', 'PELJ000101HDFRPNA1')
            ->assertJsonPath('beneficiarios.id_ine.value', 'ABC123456789012345')
            ->assertJsonPath('domicilio.seccional.value', '0234')
            ->assertJsonPath('domicilio.calle.value', 'AV REFORMA 100');

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/v1/ine/extract');
        });
    }

    public function test_handles_ocr_service_unavailable(): void
    {
        Http::fake([
            '*' => function () {
                throw new ConnectionException('Connection refused');
            },
        ]);

        $user = User::factory()->create();
        $user->assignRole('capturista');

        $response = $this->actingAs($user)
            ->postJson('/api/ocr/ine/extract', [
                'front_image' => $this->fakeJpeg('front.jpg'),
                'back_image' => $this->fakeJpeg('back.jpg'),
            ]);

        // retry(2) will attempt twice; after exhausting retries,
        // ConnectionException is caught by the controller
        $response->assertStatus(502)
            ->assertJsonPath('error_code', 'OCR_SERVICE_UNAVAILABLE');
    }

    public function test_handles_ocr_service_error(): void
    {
        Http::fake([
            '*' => Http::response([
                'error_code' => 'IMAGE_DECODE_FAILED',
                'message' => 'Invalid image payload',
                'details' => ['which' => 'back_image'],
            ], 422),
        ]);

        $user = User::factory()->create();
        $user->assignRole('capturista');

        $response = $this->actingAs($user)
            ->postJson('/api/ocr/ine/extract', [
                'front_image' => $this->fakeJpeg('front.jpg'),
                'back_image' => $this->fakeJpeg('back.jpg'),
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error_code', 'IMAGE_DECODE_FAILED');
    }
}

