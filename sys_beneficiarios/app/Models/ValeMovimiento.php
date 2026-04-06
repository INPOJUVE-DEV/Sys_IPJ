<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ValeMovimiento extends Model
{
    use HasFactory;

    public $incrementing = false;
    protected $keyType = 'string';
    protected $table = 'vale_movimientos';

    protected $fillable = [
        'id',
        'vale_bloc_id',
        'tipo',
        'from_oficina_id',
        'to_oficina_id',
        'from_usuario_uuid',
        'to_usuario_uuid',
        'actor_uuid',
        'metadata_json',
    ];

    protected $casts = [
        'metadata_json' => 'array',
    ];

    public function valeBloc()
    {
        return $this->belongsTo(ValeBloc::class);
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_uuid', 'uuid');
    }
}
