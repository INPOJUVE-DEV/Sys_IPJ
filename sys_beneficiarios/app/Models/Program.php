<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/** @use HasFactory<\Database\Factories\ProgramFactory> */
class Program extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'area',
        'active',
        'description',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];
}
