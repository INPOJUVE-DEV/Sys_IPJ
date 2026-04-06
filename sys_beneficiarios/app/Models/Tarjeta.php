<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tarjeta extends Model
{
    use HasFactory;

    public const STATUS_DISPONIBLE = 'disponible';
    public const STATUS_ASIGNADA_OFICINA = 'asignada_oficina';
    public const STATUS_ASIGNADA_USUARIO = 'asignada_usuario';
    public const STATUS_CONSUMIDA = 'consumida';
    public const STATUS_DEVUELTA = 'devuelta';
    public const STATUS_EXTRAVIADA = 'extraviada';
    public const STATUS_BLOQUEADA = 'bloqueada';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'folio',
        'estatus',
        'oficina_id',
        'usuario_uuid',
        'municipio_id',
        'beneficiario_id',
        'observaciones',
    ];

    public function oficina()
    {
        return $this->belongsTo(Oficina::class);
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'usuario_uuid', 'uuid');
    }

    public function municipio()
    {
        return $this->belongsTo(Municipio::class);
    }

    public function beneficiario()
    {
        return $this->belongsTo(Beneficiario::class);
    }

    public function movimientos()
    {
        return $this->hasMany(TarjetaMovimiento::class);
    }

    public function scopeAccessibleTo(Builder $query, User $user): Builder
    {
        if ($user->hasRole('admin')) {
            return $query;
        }

        if (! $user->oficina_id) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $sub) use ($user) {
            $sub->where('oficina_id', $user->oficina_id)
                ->orWhere('usuario_uuid', $user->uuid);
        });
    }
}
