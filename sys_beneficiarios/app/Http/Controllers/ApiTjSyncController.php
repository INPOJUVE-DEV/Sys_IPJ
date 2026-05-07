<?php

namespace App\Http\Controllers;

use App\Models\ApiTjSyncRun;
use App\Services\ApiTjSyncService;
use Illuminate\Http\Request;

class ApiTjSyncController extends Controller
{
    public function __construct(private readonly ApiTjSyncService $service)
    {
    }

    public function store(Request $request)
    {
        $run = $this->service->sync($request->user());

        if ($request->expectsJson()) {
            return response()->json([
                'sync_id' => $run->sync_id,
                'status' => $run->status,
                'request_count' => $run->request_count,
                'success_count' => $run->success_count,
                'failed_count' => $run->failed_count,
                'error_message' => $run->error_message,
            ], $run->status === ApiTjSyncRun::STATUS_FAILED ? 502 : 200);
        }

        $message = match ($run->status) {
            ApiTjSyncRun::STATUS_SUCCESS => "Sincronizacion completada correctamente. Enviados: {$run->request_count}, exitosos: {$run->success_count}.",
            ApiTjSyncRun::STATUS_FAILED => "Sincronizacion con incidencias. Enviados: {$run->request_count}, exitosos: {$run->success_count}, fallidos: {$run->failed_count}.",
            default => 'Sincronizacion con error: '.$run->error_message,
        };

        return back()->with('status', $message);
    }
}
