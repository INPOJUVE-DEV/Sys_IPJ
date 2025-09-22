<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Components\ComponentCatalogUpsertRequest;
use App\Models\ComponentCatalog;
use Illuminate\Http\JsonResponse;

class ComponentCatalogController extends Controller
{
    public function index(): JsonResponse
    {
        $this->authorize('viewAny', ComponentCatalog::class);

        $components = ComponentCatalog::orderBy('name')
            ->get()
            ->map(fn (ComponentCatalog $component) => $this->resource($component));

        return response()->json(['data' => $components]);
    }

    public function upsert(ComponentCatalogUpsertRequest $request): JsonResponse
    {
        $attributes = $request->validated();

        $component = ComponentCatalog::updateOrCreate(
            ['key' => $attributes['key']],
            [
                'name' => $attributes['name'],
                'description' => $attributes['description'] ?? null,
                'schema' => $attributes['schema'],
                'enabled' => $attributes['enabled'] ?? true,
            ]
        );

        $status = $component->wasRecentlyCreated ? 201 : 200;

        return response()->json(
            ['data' => $this->resource($component)],
            $status
        );
    }

    private function resource(ComponentCatalog $component): array
    {
        return [
            'key' => $component->key,
            'name' => $component->name,
            'description' => $component->description,
            'enabled' => $component->enabled,
            'schema' => $component->schema,
            'updated_at' => $component->updated_at?->toJson(),
        ];
    }
}

