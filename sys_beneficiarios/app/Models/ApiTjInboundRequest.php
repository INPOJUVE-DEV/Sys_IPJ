<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApiTjInboundRequest extends Model
{
    public const STATUS_RECEIVED = 'received';
    public const STATUS_CREATED = 'created';
    public const STATUS_ALREADY_PROCESSED = 'already_processed';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_CONFLICT = 'conflict';
    public const STATUS_ERROR = 'error';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'external_request_id',
        'source',
        'curp_masked',
        'beneficiario_id',
        'status',
        'request_hash',
        'response_code',
        'error_message',
        'received_at',
        'processed_at',
        'created_by_system',
        'payload_json',
    ];

    protected $casts = [
        'received_at' => 'datetime',
        'processed_at' => 'datetime',
        'payload_json' => 'encrypted:array',
    ];

    public function beneficiario(): BelongsTo
    {
        return $this->belongsTo(Beneficiario::class);
    }
}
