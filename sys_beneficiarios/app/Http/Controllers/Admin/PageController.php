<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Pages\RollbackPageRequest;
use App\Http\Requests\Admin\Pages\StorePageRequest;
use App\Http\Requests\Admin\Pages\UpdatePageDraftRequest;
use App\Models\Page;
use App\Models\PageVersion;
use App\Services\PageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PageController extends Controller
{
    public function __construct(private readonly PageService $pageService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Page::class);

        $pages = Page::with(['publishedVersion'])
            ->orderBy('slug')
            ->get()
            ->map(fn (Page $page) => $this->pageResource($page));

        return response()->json(['data' => $pages]);
    }

    public function store(StorePageRequest $request): JsonResponse
    {
        [$page, $draft] = $this->pageService->createPage($request->validated(), $request->user());

        return response()->json([
            'data' => array_merge(
                $this->pageResource($page->fresh(['publishedVersion'])),
                ['draft' => $this->versionResource($draft)],
            ),
        ], 201);
    }

    public function showDraft(Page $page, Request $request): JsonResponse
    {
        $this->authorize('update', $page);

        $draft = $this->pageService->showDraft($page, $request->user());

        return response()->json([
            'data' => $this->versionResource($draft),
        ]);
    }

    public function updateDraft(UpdatePageDraftRequest $request, Page $page): JsonResponse
    {
        $draft = $this->pageService->updateDraft($page, $request->validated(), $request->user());

        return response()->json([
            'data' => $this->versionResource($draft),
        ]);
    }

    public function publish(Page $page, Request $request): JsonResponse
    {
        $this->authorize('publish', $page);

        [$published, $draft] = $this->pageService->publish($page, $request->user());

        return response()->json([
            'published' => $this->versionResource($published),
            'draft' => $this->versionResource($draft),
        ]);
    }

    public function versions(Page $page): JsonResponse
    {
        $this->authorize('viewVersions', $page);

        $versions = $page->versions()
            ->orderByDesc('version')
            ->get()
            ->map(fn (PageVersion $version) => $this->versionResource($version));

        return response()->json(['data' => $versions]);
    }

    public function rollback(RollbackPageRequest $request, Page $page): JsonResponse
    {
        $target = $page->versions()
            ->where('version', $request->integer('version'))
            ->firstOrFail();

        [$published, $draft] = $this->pageService->rollback($page, $target, $request->user());

        return response()->json([
            'published' => $this->versionResource($published),
            'draft' => $this->versionResource($draft),
        ]);
    }

    private function pageResource(Page $page): array
    {
        $page->loadMissing('publishedVersion');

        return [
            'id' => $page->id,
            'slug' => $page->slug,
            'published_version' => $page->publishedVersion
                ? $this->versionResource($page->publishedVersion)
                : null,
            'created_at' => $page->created_at?->toJson(),
            'updated_at' => $page->updated_at?->toJson(),
        ];
    }

    private function versionResource(PageVersion $version): array
    {
        return [
            'id' => $version->id,
            'version' => $version->version,
            'status' => $version->status,
            'title' => $version->title,
            'layout_json' => $version->layout_json,
            'notes' => $version->notes,
            'published_at' => $version->published_at?->toJson(),
            'created_at' => $version->created_at?->toJson(),
        ];
    }
}


