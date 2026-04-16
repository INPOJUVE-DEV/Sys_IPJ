<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Municipio extends Model
{
    use HasFactory;

    protected $fillable = [
        'clave', 'nombre', 'region', 'oficina_id',
    ];

    public function beneficiarios()
    {
        return $this->hasMany(Beneficiario::class);
    }

    public function oficina()
    {
        return $this->belongsTo(Oficina::class, 'oficina_id');
    }

    public function secciones()
    {
        return $this->hasMany(Seccion::class);
    }
}
