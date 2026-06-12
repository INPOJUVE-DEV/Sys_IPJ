<?php

namespace App\Jobs\Integrations\ApiTj;

use App\Models\Integrations\IntegrationSyncRun;
use App\Services\Integrations\ApiTj\CardholderSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RunCardholderSyncJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public function __construct(
        public readonly string $syncRunId,
    ) {
    }

    public function handle(CardholderSyncService $service): void
    {
        $run = IntegrationSyncRun::query()->find($this->syncRunId);

        if (! $run) {
            return;
        }

        $service->run($run);
    }
}
