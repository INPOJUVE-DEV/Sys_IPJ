<?php

namespace App\Models\Integrations;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IntegrationInboundRequest extends Model
{
    use HasFactory;

    public const STATUS_RECEIVED = 'received';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_FAILED = 'failed';
    public const STATUS_ALREADY_PROCESSED = 'already_processed';

    protected $table = 'integration_inbound_requests';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'source_system',
        'external_request_id',
        'operation',
        'request_hash',
        'request_payload_encrypted',
        'status',
        'response_code',
        'response_body',
        'error_message',
        'received_at',
        'processed_at',
    ];

    protected $casts = [
        'response_code' => 'integer',
        'response_body' => 'array',
        'received_at' => 'datetime',
        'processed_at' => 'datetime',
    ];
}
