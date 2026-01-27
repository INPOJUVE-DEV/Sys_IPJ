<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Programa extends Model
{
    use HasFactory;

    protected $fillable = [
        'nombre',
        'slug',
        'tipo_periodo',
        'renovable',
        'activo',
    ];

    protected $casts = [
        'renovable' => 'boolean',
        'activo' => 'boolean',
    ];

    public function inscripciones()
    {
        return $this->hasMany(Inscripcion::class);
    }
}
