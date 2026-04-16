<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Oficina extends Model
{
    use HasFactory;

    public const TIPO_CENTRAL = 'central';
    public const TIPO_DELEGACION = 'delegacion';

    protected $fillable = [
        'nombre',
        'tipo',
        'region',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    public function users()
    {
        return $this->hasMany(User::class, 'oficina_id');
    }

    public function municipios()
    {
        return $this->hasMany(Municipio::class, 'oficina_id');
    }

    public function tarjetas()
    {
        return $this->hasMany(Tarjeta::class);
    }

    public function valeBlocs()
    {
        return $this->hasMany(ValeBloc::class);
    }
}
