<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Evento extends Model
{
    use HasFactory, SoftDeletes;

    public const ROL_ANFITRION = 'anfitrion';
    public const ROL_INVITADO = 'invitado';

    protected $fillable = [
        'evento_tipo_id',
        'municipio_id',
        'oficina_id',
        'created_by',
        'descripcion',
        'lugar',
        'rol_participacion',
        'total_asistentes',
        'evidencia_url',
    ];

    protected $casts = [
        'total_asistentes' => 'integer',
    ];

    public static function rolesParticipacion(): array
    {
        return [
            self::ROL_ANFITRION => 'Anfitrion',
            self::ROL_INVITADO => 'Invitado',
        ];
    }

    public function tipo()
    {
        return $this->belongsTo(EventoTipo::class, 'evento_tipo_id');
    }

    public function municipio()
    {
        return $this->belongsTo(Municipio::class);
    }

    public function oficina()
    {
        return $this->belongsTo(Oficina::class);
    }

    public function creador()
    {
        return $this->belongsTo(User::class, 'created_by', 'uuid');
    }
}
