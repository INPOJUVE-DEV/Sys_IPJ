<?php

namespace App\Models\Integrations;

use App\Models\Beneficiario;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IntegrationSyncItem extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_SENT = 'sent';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_SKIPPED = 'skipped';
    public const STATUS_ERROR = 'error';

    protected $table = 'integration_sync_items';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'sync_run_id',
        'beneficiario_id',
        'payload_hash',
        'status',
        'response_code',
        'response_body',
        'error_message',
    ];

    protected $casts = [
        'response_code' => 'integer',
        'response_body' => 'array',
    ];

    public function syncRun()
    {
        return $this->belongsTo(IntegrationSyncRun::class, 'sync_run_id');
    }

    public function beneficiario()
    {
        return $this->belongsTo(Beneficiario::class, 'beneficiario_id');
    }
}
