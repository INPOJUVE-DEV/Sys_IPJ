<?php

namespace App\Models\Integrations;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IntegrationClient extends Model
{
    use HasFactory;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';

    protected $table = 'integration_clients';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'client_code',
        'name',
        'status',
        'allowed_scopes',
        'ip_allowlist',
        'last_used_at',
    ];

    protected $casts = [
        'allowed_scopes' => 'array',
        'ip_allowlist' => 'array',
        'last_used_at' => 'datetime',
    ];

    public function keys()
    {
        return $this->hasMany(IntegrationClientKey::class, 'client_id');
    }

    public function jtiLogs()
    {
        return $this->hasMany(IntegrationJtiLog::class, 'client_id');
    }
}
