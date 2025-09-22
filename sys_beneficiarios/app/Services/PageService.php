<?php

namespace App\Services;

use App\Models\Page;
use App\Models\PageVersion;
use App\Services\ComponentRegistry;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PageService
{
    public function __construct(private readonly ComponentRegistry $registry)
    {
    }

    public function createPage(array $data, Authenticatable $user): array
    {
        return DB::transaction(function () use ($data, $user) {
            $this->registry->assertValidLayout($data['layout_json']);

            $page = Page::create([
                'slug' => $data['slug'],
                'created_by' => $user->getAuthIdentifier(),
                'updated_by' => $user->getAuthIdentifier(),
            ]);

            $draft = $page->versions()->create([
                'version' => 1,
                'status' => PageVersion::STATUS_DRAFT,
                'title' => $data['title'],
                'layout_json' => $data['layout_json'],
                'notes' => $data['notes'] ?? null,
                'created_by' => $user->getAuthIdentifier(),
            ]);

            return [$page->fresh(['versions']), $draft];
        });
    }

    public function showDraft(Page $page, Authenticatable $user): PageVersion
    {
        return DB::transaction(fn () => $this->acquireDraft($page, $user));
    }

    public function updateDraft(Page $page, array $data, Authenticatable $user): PageVersion
    {
        return DB::transaction(function () use ($page, $data, $user) {
            $this->registry->assertValidLayout($data['layout_json']);

            $draft = $this->acquireDraft($page, $user);

            $draft->fill([
                'title' => $data['title'],
                'layout_json' => $data['layout_json'],
                'notes' => $data['notes'] ?? null,
            ])->save();

            $page->forceFill(['updated_by' => $user->getAuthIdentifier()])->save();

            return $draft->fresh();
        });
    }

    public function publish(Page $page, Authenticatable $user): array
    {
        return DB::transaction(function () use ($page, $user) {
            $draft = $page->versions()->draft()->lockForUpdate()->orderByDesc('version')->first();

            if (! $draft) {
                throw ValidationException::withMessages([
                    'draft' => 'No hay un borrador disponible para publicar.',
                ]);
            }

            $this->registry->assertValidLayout($draft->layout_json ?? []);

            if ($page->publishedVersion) {
                $page->publishedVersion->update(['status' => PageVersion::STATUS_ARCHIVED]);
            }

            $draft->update([
                'status' => PageVersion::STATUS_PUBLISHED,
                'published_at' => now(),
            ]);

            $page->update([
                'published_version_id' => $draft->id,
                'updated_by' => $user->getAuthIdentifier(),
            ]);

            $newDraft = $page->versions()->create([
                'version' => $draft->version + 1,
                'status' => PageVersion::STATUS_DRAFT,
                'title' => $draft->title,
                'layout_json' => $draft->layout_json,
                'notes' => null,
                'created_by' => $user->getAuthIdentifier(),
            ]);

            return [$draft->fresh(), $newDraft];
        });
    }

    public function rollback(Page $page, PageVersion $targetVersion, Authenticatable $user): array
    {
        if ($targetVersion->page_id !== $page->id) {
            throw ValidationException::withMessages([
                'version' => 'La versión solicitada no pertenece a la página.',
            ]);
        }

        if ($targetVersion->status === PageVersion::STATUS_DRAFT) {
            throw ValidationException::withMessages([
                'version' => 'No se puede hacer rollback a un borrador.',
            ]);
        }

        return DB::transaction(function () use ($page, $targetVersion, $user) {
            $page->versions()->draft()->lockForUpdate()->get()->each->delete();

            $this->registry->assertValidLayout($targetVersion->layout_json ?? []);

            if ($page->publishedVersion) {
                $page->publishedVersion->update(['status' => PageVersion::STATUS_ARCHIVED]);
            }

            $newVersionNumber = $this->nextVersionNumber($page);

            $published = $page->versions()->create([
                'version' => $newVersionNumber,
                'status' => PageVersion::STATUS_PUBLISHED,
                'title' => $targetVersion->title,
                'layout_json' => $targetVersion->layout_json,
                'notes' => $targetVersion->notes,
                'created_by' => $user->getAuthIdentifier(),
                'published_at' => now(),
            ]);

            $page->update([
                'published_version_id' => $published->id,
                'updated_by' => $user->getAuthIdentifier(),
            ]);

            $nextDraft = $page->versions()->create([
                'version' => $published->version + 1,
                'status' => PageVersion::STATUS_DRAFT,
                'title' => $published->title,
                'layout_json' => $published->layout_json,
                'notes' => null,
                'created_by' => $user->getAuthIdentifier(),
            ]);

            return [$published->fresh(), $nextDraft];
        });
    }

    protected function acquireDraft(Page $page, Authenticatable $user): PageVersion
    {
        $draft = $page->versions()->draft()->lockForUpdate()->orderByDesc('version')->first();

        if ($draft) {
            return $draft;
        }

        $versionNumber = $this->nextVersionNumber($page);
        $source = $page->latestPublished();

        return $page->versions()->create([
            'version' => $versionNumber,
            'status' => PageVersion::STATUS_DRAFT,
            'title' => $source?->title ?? 'Borrador',
            'layout_json' => $source?->layout_json ?? [],
            'notes' => null,
            'created_by' => $user->getAuthIdentifier(),
        ]);
    }

    protected function nextVersionNumber(Page $page): int
    {
        return (int) $page->versions()->max('version') + 1;
    }
}
