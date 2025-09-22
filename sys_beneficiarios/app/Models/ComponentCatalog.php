<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ComponentCatalog extends Model
{
    use HasFactory;

    protected $table = 'components_catalog';

    protected $fillable = [
        'key',
        'name',
        'description',
        'schema',
        'enabled',
    ];

    protected $casts = [
        'schema' => 'array',
        'enabled' => 'boolean',
    ];

    public function scopeEnabled($query)
    {
        return $query->where('enabled', true);
    }
}
