<?php

namespace App\Http\Controllers;

use App\Services\ThemeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class ThemePublicController extends Controller
{
    public function __construct(private readonly ThemeService $themeService)
    {
    }

    public function __invoke(): JsonResponse
    {
        $payload = Cache::rememberForever(ThemeService::CACHE_KEY.'.payload', function () {
            $theme = $this->themeService->getActive();

            if (! $theme) {
                return null;
            }

            return [
                'name' => $theme->name,
                'tokens' => $theme->tokens,
                'updated_at' => $theme->updated_at?->toJson(),
            ];
        });

        if (! $payload) {
            return response()->json(['data' => null])
                ->header('Cache-Control', 'public, max-age=60');
        }

        return response()
            ->json(['data' => $payload])
            ->header('Cache-Control', 'public, max-age=300');
    }
}