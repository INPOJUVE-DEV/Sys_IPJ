<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApiTjSyncRun extends Model
{
    public const STATUS_RUNNING = 'running';
    public const STATUS_SUCCESS = 'success';
    public const STATUS_PARTIAL = 'partial';
    public const STATUS_ERROR = 'error';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'sync_id',
        'executed_by',
        'role',
        'started_at',
        'finished_at',
        'request_count',
        'success_count',
        'failed_count',
        'api_status_code',
        'api_response_body',
        'status',
        'error_message',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'executed_by', 'uuid');
    }
}
