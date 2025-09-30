<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use Carbon\Carbon;

class Beneficiario extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'folio_tarjeta',
        'nombre',
        'apellido_paterno',
        'apellido_materno',
        'curp',
        'fecha_nacimiento',
        'edad',
        'sexo',
        'discapacidad',
        'id_ine',
        'telefono',
        'municipio_id',
        'seccional',
        'distrito_local',
        'distrito_federal',
        'created_by',
    ];

    protected $casts = [
        'fecha_nacimiento' => 'date',
        'discapacidad' => 'boolean',
        'edad' => 'integer',
    ];

    protected static $logName = 'beneficiarios';
    protected static $logFillable = true;
    protected static $logOnlyDirty = true;
    protected static $recordEvents = ['created','updated','deleted'];

    protected static function booted()
    {
        static::saving(function (self $model) {
            if ($model->fecha_nacimiento) {
                $dob = Carbon::parse($model->fecha_nacimiento);
                $model->edad = $dob->age;
            }
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('beneficiarios')
            ->logFillable()
            ->logOnlyDirty();
    }

    // Relations
    public function creador()
    {
        return $this->belongsTo(User::class, 'created_by', 'uuid');
    }

    public function municipio()
    {
        return $this->belongsTo(Municipio::class);
    }

    public function domicilio()
    {
        return $this->hasOne(Domicilio::class, 'beneficiario_id');
    }

    public function salud360Assignment()
    {
        return $this->hasOne(Salud360Assignment::class, 'beneficiario_id');
    }

    public function salud360Sessions()
    {
        return $this->hasMany(Salud360Session::class, 'beneficiario_id');
    }
}
