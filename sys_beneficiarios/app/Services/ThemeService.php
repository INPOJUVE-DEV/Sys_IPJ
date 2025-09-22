<?php

namespace App\Services;

use App\Models\Theme;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ThemeService
{
    public const CACHE_KEY = 'theme.current';

    public function getActive(): ?Theme
    {
        return Cache::rememberForever(self::CACHE_KEY, function () {
            return Theme::active()->first();
        });
    }

    public function updateActive(array $attributes): Theme
    {
        return DB::transaction(function () use ($attributes) {
            $theme = Theme::active()->lockForUpdate()->first();

            if ($theme) {
                $theme->fill([
                    'name' => $attributes['name'],
                    'tokens' => $attributes['tokens'],
                ])->save();
            } else {
                Theme::query()->update(['is_active' => false]);

                $theme = Theme::create([
                    'name' => $attributes['name'],
                    'tokens' => $attributes['tokens'],
                    'is_active' => true,
                ]);
            }

            Cache::forget(self::CACHE_KEY);
            Cache::forget(self::CACHE_KEY.'.payload');

            $theme = $theme->fresh();
            Cache::forever(self::CACHE_KEY, $theme);

            return $theme;
        });
    }
}