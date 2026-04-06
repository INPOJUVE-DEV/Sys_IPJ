<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProteccionMovimiento extends Model
{
    use HasFactory;

    public $incrementing = false;
    protected $keyType = 'string';
    protected $table = 'proteccion_movimientos';

    protected $fillable = [
        'id',
        'proteccion_id',
        'tipo',
        'actor_uuid',
        'from_usuario_uuid',
        'to_usuario_uuid',
        'beneficiario_id',
        'metadata_json',
    ];

    protected $casts = [
        'metadata_json' => 'array',
    ];

    public function proteccion()
    {
        return $this->belongsTo(Proteccion::class);
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_uuid', 'uuid');
    }

    public function beneficiario()
    {
        return $this->belongsTo(Beneficiario::class);
    }
}
