<?php

namespace App\Models\Integrations;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IntegrationSyncRun extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_QUEUED = 'queued';
    public const STATUS_RUNNING = 'running';
    public const STATUS_SUCCESS = 'success';
    public const STATUS_PARTIAL = 'partial';
    public const STATUS_FAILED = 'failed';
    public const STATUS_CANCELLED = 'cancelled';

    protected $table = 'integration_sync_runs';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'target_system',
        'operation',
        'status',
        'requested_by',
        'started_at',
        'finished_at',
        'total_items',
        'success_count',
        'failed_count',
        'skipped_count',
        'error_message',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'total_items' => 'integer',
        'success_count' => 'integer',
        'failed_count' => 'integer',
        'skipped_count' => 'integer',
    ];

    public function requestedBy()
    {
        return $this->belongsTo(User::class, 'requested_by', 'uuid');
    }

    public function items()
    {
        return $this->hasMany(IntegrationSyncItem::class, 'sync_run_id');
    }
}
