<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Models\PageVersion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PagePublicController extends Controller
{
    public function __invoke(Request $request, string $slug): JsonResponse
    {
        $page = Page::where('slug', $slug)->firstOrFail();

        $version = $request->filled('version')
            ? $page->versions()
                ->where('version', $request->integer('version'))
                ->whereIn('status', [PageVersion::STATUS_PUBLISHED, PageVersion::STATUS_ARCHIVED])
                ->firstOrFail()
            : $page->publishedVersion;

        if (! $version) {
            abort(404, 'Página no publicada.');
        }

        $payload = [
            'slug' => $page->slug,
            'version' => $version->version,
            'status' => $version->status,
            'title' => $version->title,
            'layout_json' => $version->layout_json,
            'notes' => $version->notes,
            'published_at' => $version->published_at?->toJson(),
            'updated_at' => $version->updated_at?->toJson(),
        ];

        return response()
            ->json($payload)
            ->header('Cache-Control', 'public, max-age=60');
    }
}
