<x-app-layout>
    <x-slot name="header">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
            <div>
                <h2 class="h4 m-0">Detalle de sincronizacion API_TJ</h2>
                <div class="text-muted small font-monospace">{{ $syncRun->sync_id }}</div>
            </div>
            <a href="{{ route('admin.api-tj.sync-runs.index') }}" class="btn btn-outline-primary">Volver</a>
        </div>
    </x-slot>

    @php
        $requestPayload = $syncRun->request_payload_json ?? [];
        $responsePayload = $syncRun->api_response_body;
    @endphp

    <div class="row g-3">
        <div class="col-12 col-xl-4">
            <div class="card shadow-sm">
                <div class="card-header fw-semibold">Resumen</div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-5">Estatus</dt><dd class="col-sm-7">{{ $syncRun->status }}</dd>
                        <dt class="col-sm-5">Actor</dt><dd class="col-sm-7">{{ $syncRun->actor?->name ?: 'Sistema' }}</dd>
                        <dt class="col-sm-5">Inicio</dt><dd class="col-sm-7">{{ optional($syncRun->started_at)->format('Y-m-d H:i:s') ?: 'N/D' }}</dd>
                        <dt class="col-sm-5">Fin</dt><dd class="col-sm-7">{{ optional($syncRun->finished_at)->format('Y-m-d H:i:s') ?: 'N/D' }}</dd>
                        <dt class="col-sm-5">HTTP</dt><dd class="col-sm-7">{{ $syncRun->api_status_code ?: 'N/D' }}</dd>
                        <dt class="col-sm-5">Enviados</dt><dd class="col-sm-7">{{ $syncRun->request_count }}</dd>
                        <dt class="col-sm-5">Exitosos</dt><dd class="col-sm-7">{{ $syncRun->success_count }}</dd>
                        <dt class="col-sm-5">Fallidos</dt><dd class="col-sm-7">{{ $syncRun->failed_count }}</dd>
                        <dt class="col-sm-5">Error</dt><dd class="col-sm-7">{{ $syncRun->error_message ?: 'Ninguno' }}</dd>
                    </dl>
                </div>
            </div>
        </div>
        <div class="col-12 col-xl-4">
            <div class="card shadow-sm h-100">
                <div class="card-header fw-semibold">Payload enviado</div>
                <div class="card-body">
                    <pre class="small mb-0">{{ json_encode($requestPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                </div>
            </div>
        </div>
        <div class="col-12 col-xl-4">
            <div class="card shadow-sm h-100">
                <div class="card-header fw-semibold">Respuesta API_TJ</div>
                <div class="card-body">
                    <pre class="small mb-0">{{ $responsePayload ?: 'Sin respuesta registrada.' }}</pre>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
