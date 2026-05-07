<?php

namespace App\Models;

use App\Support\ApiTjHelper;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Beneficiario extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    public const STATUS_ACTIVE = 'active';
    public const API_TJ_SYNC_STATUS_PENDING_SYNC = 'pending_sync';
    public const API_TJ_SYNC_STATUS_SYNCED = 'synced';
    public const API_TJ_SYNC_STATUS_SYNC_FAILED = 'sync_failed';
    public const API_TJ_SYNC_STATUS_PENDING_DATA = 'pending_data';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'folio_tarjeta',
        'tarjeta_id',
        'nombre',
        'apellido_paterno',
        'apellido_materno',
        'curp',
        'curp_hash',
        'fecha_nacimiento',
        'edad',
        'sexo',
        'discapacidad',
        'id_ine',
        'telefono',
        'email',
        'municipio_id',
        'seccion_id',
        'created_by',
        'source_system',
        'source_external_request_id',
        'status',
        'api_tj_sync_status',
        'api_tj_sync_attempts',
        'api_tj_last_sync_error',
        'api_tj_last_synced_at',
    ];

    protected $casts = [
        'fecha_nacimiento' => 'date',
        'discapacidad' => 'boolean',
        'edad' => 'integer',
        'api_tj_sync_attempts' => 'integer',
        'api_tj_last_synced_at' => 'datetime',
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

    public function tarjeta()
    {
        return $this->belongsTo(Tarjeta::class);
    }

    public function municipio()
    {
        return $this->belongsTo(Municipio::class);
    }

    public function seccion()
    {
        return $this->belongsTo(Seccion::class);
    }

    public function domicilio()
    {
        return $this->hasOne(Domicilio::class, 'beneficiario_id');
    }

    public function inscripciones()
    {
        return $this->hasMany(Inscripcion::class);
    }

    public function protecciones()
    {
        return $this->hasMany(Proteccion::class);
    }

    public function hasCompleteApiTjProfile(): bool
    {
        return $this->status === self::STATUS_ACTIVE
            && ApiTjHelper::isValidCurp($this->curp)
            && filled($this->curp_hash)
            && filled(trim((string) $this->nombre))
            && filled($this->apiTjTarjetaNumero());
    }

    public function apiTjTarjetaNumero(): ?string
    {
        $tarjetaNumero = $this->tarjeta?->folio ?: trim((string) $this->folio_tarjeta);

        return $tarjetaNumero !== '' ? $tarjetaNumero : null;
    }

    public static function apiTjSyncRelevantAttributes(): array
    {
        return [
            'curp',
            'nombre',
            'folio_tarjeta',
            'tarjeta_id',
            'email',
            'status',
        ];
    }

}
