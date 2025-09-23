<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use App\Models\Municipio;

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

    public static function ensureDefaultSites(): Collection
    {
        $defaults = [
            'San Luis Potosí',
            'Matehuala',
            'Rioverde',
            'Ciudad Valles',
        ];

        foreach ($defaults as $name) {
            $municipio = Municipio::query()
                ->where('nombre', 'like', $name)
                ->orWhere('nombre', 'like', str_replace('ó', 'o', $name))
                ->first();

            static::firstOrCreate(
                ['name' => $name],
                [
                    'state' => 'San Luis Potosí',
                    'city' => $municipio->nombre ?? $name,
                    'address' => 'Por definir',
                    'active' => true,
                ]
            );
        }

        return static::orderBy('name')->pluck('name', 'id');
    }
}
