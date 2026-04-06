<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Proteccion extends Model
{
    use HasFactory;

    public const STATUS_DISPONIBLE = 'disponible';
    public const STATUS_PRESTADA = 'prestada';
    public const STATUS_INACTIVA = 'inactiva';

    public $incrementing = false;
    protected $keyType = 'string';
    protected $table = 'protecciones';

    protected $fillable = [
        'id',
        'tipo',
        'numero_inventario',
        'estatus',
        'usuario_uuid',
        'beneficiario_id',
        'prestada_at',
        'observaciones',
    ];

    protected $casts = [
        'prestada_at' => 'datetime',
    ];

    public function usuario()
    {
        return $this->belongsTo(User::class, 'usuario_uuid', 'uuid');
    }

    public function beneficiario()
    {
        return $this->belongsTo(Beneficiario::class);
    }

    public function movimientos()
    {
        return $this->hasMany(ProteccionMovimiento::class);
    }

    public function scopeAccessibleTo(Builder $query, User $user): Builder
    {
        if ($user->hasRole('admin')) {
            return $query;
        }

        return $query->where('usuario_uuid', $user->uuid);
    }
}
