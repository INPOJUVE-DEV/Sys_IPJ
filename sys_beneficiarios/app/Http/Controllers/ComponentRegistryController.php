<?php

namespace App\Http\Controllers;

use App\Services\ComponentRegistry;
use Illuminate\Http\JsonResponse;

class ComponentRegistryController extends Controller
{
    public function __construct(private readonly ComponentRegistry $registry)
    {
    }

    public function __invoke(): JsonResponse
    {
        $components = $this->registry->registry()
            ->map(fn ($component) => [
                'key' => $component->key,
                'name' => $component->name,
                'description' => $component->description,
                'schema' => $component->schema,
            ])
            ->values();

        return response()
            ->json(['data' => $components])
            ->header('Cache-Control', 'public, max-age=300');
    }
}
