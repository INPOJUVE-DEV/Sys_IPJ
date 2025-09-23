<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** @use HasFactory<\Database\Factories\VolGroupFactory> */
class VolGroup extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'program_id',
        'site_id',
        'code',
        'name',
        'type',
        'schedule_template',
        'start_date',
        'end_date',
        'capacity',
        'state',
        'created_by',
        'updated_by',
    ];

    protected $appends = ['available_slots'];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'capacity' => 'integer',
        'deleted_at' => 'datetime',
    ];

    public function scopeWithAvailability(Builder $query): Builder
    {
        return $query->withCount([
            'enrollments as active_enrollments' => fn (Builder $q) => $q->where('status', 'inscrito'),
        ]);
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo('App\Models\Program', 'program_id');
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(VolSite::class, 'site_id');
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(VolEnrollment::class, 'group_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function getAvailableSlotsAttribute(): int
    {
        $capacity = (int) ($this->capacity ?? 0);
        $active = (int) ($this->active_enrollments ?? 0);
        return max(0, $capacity - $active);
    }
}