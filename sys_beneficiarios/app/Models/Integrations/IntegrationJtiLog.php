<?php

namespace App\Models\Integrations;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IntegrationJtiLog extends Model
{
    use HasFactory;

    protected $table = 'integration_jti_logs';

    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'id',
        'client_id',
        'issuer',
        'jti',
        'expires_at',
        'created_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function client()
    {
        return $this->belongsTo(IntegrationClient::class, 'client_id');
    }
}
