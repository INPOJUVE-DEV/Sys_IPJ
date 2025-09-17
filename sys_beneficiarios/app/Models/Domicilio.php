<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Domicilio extends Model
{
    use HasFactory, LogsActivity;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'beneficiario_id',
        'calle',
        'numero_ext',
        'numero_int',
        'colonia',
        'municipio_id',
        'codigo_postal',
        'seccional',
        'distrito_local',
        'distrito_federal',
    ];

    protected static $logName = 'domicilios';
    protected static $logFillable = true;
    protected static $logOnlyDirty = true;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('domicilios')
            ->logFillable()
            ->logOnlyDirty();
    }

    public function beneficiario()
    {
        return $this->belongsTo(Beneficiario::class, 'beneficiario_id');
    }
}
