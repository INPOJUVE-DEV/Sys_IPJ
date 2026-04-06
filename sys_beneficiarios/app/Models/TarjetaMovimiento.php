<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TarjetaMovimiento extends Model
{
    use HasFactory;

    public $incrementing = false;
    protected $keyType = 'string';
    protected $table = 'tarjeta_movimientos';

    protected $fillable = [
        'id',
        'tarjeta_id',
        'tipo',
        'from_oficina_id',
        'to_oficina_id',
        'from_usuario_uuid',
        'to_usuario_uuid',
        'beneficiario_id',
        'actor_uuid',
        'metadata_json',
    ];

    protected $casts = [
        'metadata_json' => 'array',
    ];

    public function tarjeta()
    {
        return $this->belongsTo(Tarjeta::class);
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_uuid', 'uuid');
    }
}
