<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ValeBloc extends Model
{
    use HasFactory;

    public const STATUS_DISPONIBLE = 'disponible';
    public const STATUS_ASIGNADO_OFICINA = 'asignado_oficina';
    public const STATUS_ASIGNADO_USUARIO = 'asignado_usuario';
    public const STATUS_CERRADO = 'cerrado';
    public const STATUS_DEVUELTO = 'devuelto';
    public const STATUS_EXTRAVIADO = 'extraviado';
    public const STATUS_BLOQUEADO = 'bloqueado';

    public $incrementing = false;
    protected $keyType = 'string';
    protected $table = 'vale_blocs';

    protected $fillable = [
        'id',
        'folio_inicio',
        'folio_fin',
        'cantidad',
        'estatus',
        'oficina_id',
        'usuario_uuid',
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

    public function movimientos()
    {
        return $this->hasMany(ValeMovimiento::class);
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
