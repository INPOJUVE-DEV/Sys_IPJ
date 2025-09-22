<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Page extends Model
{
    use HasFactory;

    protected $fillable = [
        'slug',
        'created_by',
        'updated_by',
        'published_version_id',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function versions(): HasMany
    {
        return $this->hasMany(PageVersion::class);
    }

    public function publishedVersion(): BelongsTo
    {
        return $this->belongsTo(PageVersion::class, 'published_version_id');
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function currentDraft(): ?PageVersion
    {
        return $this->versions()
            ->draft()
            ->orderByDesc('version')
            ->first();
    }

    public function latestPublished(): ?PageVersion
    {
        return $this->versions()
            ->published()
            ->orderByDesc('version')
            ->first();
    }
}
