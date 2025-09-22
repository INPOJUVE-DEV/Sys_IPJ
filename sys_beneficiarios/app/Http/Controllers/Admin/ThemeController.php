<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Themes\UpdateThemeRequest;
use App\Models\Theme;
use App\Services\ThemeService;
use Illuminate\Http\JsonResponse;

class ThemeController extends Controller
{
    public function __construct(private readonly ThemeService $themeService)
    {
    }

    public function show(): JsonResponse
    {
        $this->authorize('viewAny', Theme::class);

        $theme = $this->themeService->getActive();

        if (! $theme) {
            return response()->json(['data' => null]);
        }

        return response()->json(['data' => $this->resource($theme)]);
    }

    public function update(UpdateThemeRequest $request): JsonResponse
    {
        $this->authorize('update', Theme::class);

        $theme = $this->themeService->updateActive($request->validated());

        return response()->json(['data' => $this->resource($theme)]);
    }

    private function resource(Theme $theme): array
    {
        return [
            'name' => $theme->name,
            'tokens' => $theme->tokens,
            'updated_at' => $theme->updated_at?->toJson(),
        ];
    }
}