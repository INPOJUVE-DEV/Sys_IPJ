<?php

namespace App\Http\Controllers;

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

        $message = match ($run->status) {
            'success' => "Sincronizacion completada correctamente. Enviados: {$run->request_count}, exitosos: {$run->success_count}.",
            'partial' => "Sincronizacion parcial. Enviados: {$run->request_count}, exitosos: {$run->success_count}, fallidos: {$run->failed_count}.",
            default => 'Sincronizacion con error: '.$run->error_message,
        };

        return back()->with('status', $message);
    }
}
