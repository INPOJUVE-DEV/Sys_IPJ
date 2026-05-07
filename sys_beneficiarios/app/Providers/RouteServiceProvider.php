<?php

namespace App\Providers;

use App\Http\Controllers\Api\ApiTjInboundRequestController;
use App\Http\Controllers\ApiTjSyncController;
use App\Models\ApiTjInboundRequest;
use App\Models\ApiTjSyncRun;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

class RouteServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Route::bind('requestRecord', function (string $value) {
            abort_unless(Schema::hasTable('api_tj_inbound_requests'), 503, 'Faltan migraciones de API_TJ para consultar solicitudes inbound.');

            return ApiTjInboundRequest::query()->whereKey($value)->firstOrFail();
        });

        Route::bind('syncRun', function (string $value) {
            abort_unless(Schema::hasTable('api_tj_sync_runs'), 503, 'Faltan migraciones de API_TJ para consultar sincronizaciones.');

            return ApiTjSyncRun::query()->whereKey($value)->firstOrFail();
        });

        $this->routes(function () {
            Route::prefix('api/v1')
                ->middleware(['api', 'etag'])
                ->group(base_path('routes/api.php'));

            Route::prefix('api')
                ->middleware(['api', 'etag'])
                ->group(function () {
                    Route::middleware(['api_tj.jwt', 'throttle:30,1'])
                        ->post('/api-tj/inbound', [ApiTjInboundRequestController::class, 'store']);

                    Route::middleware(['auth:sanctum', 'throttle:15,1'])
                        ->post('/api-tj/sync', [ApiTjSyncController::class, 'store']);
                });
        });
    }
}
