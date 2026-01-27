<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Inscripcion extends Model
{
    use HasFactory;

    protected $table = 'inscripciones';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'beneficiario_id',
        'programa_id',
        'periodo',
        'estatus',
        'fecha_renovacion',
        'created_by',
    ];

    protected $casts = [
        'fecha_renovacion' => 'datetime',
    ];

    public function beneficiario()
    {
        return $this->belongsTo(Beneficiario::class);
    }

    public function programa()
    {
        return $this->belongsTo(Programa::class);
    }

    public function creador()
    {
        return $this->belongsTo(User::class, 'created_by', 'uuid');
    }
}
