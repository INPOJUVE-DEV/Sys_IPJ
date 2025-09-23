<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** @use HasFactory<\Database\Factories\VolSiteFactory> */
class VolSite extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'name',
        'state',
        'city',
        'address',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function groups(): HasMany
    {
        return $this->hasMany(VolGroup::class, 'site_id');
    }
}