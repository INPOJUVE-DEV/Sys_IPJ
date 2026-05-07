<x-app-layout>
    <x-slot name="header">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
            <div>
                <h2 class="h4 m-0">Detalle de solicitud API_TJ</h2>
                <div class="text-muted small font-monospace">{{ $requestRecord->external_request_id }}</div>
            </div>
            <a href="{{ route('admin.api-tj.requests.index') }}" class="btn btn-outline-primary">Volver</a>
        </div>
    </x-slot>

    @if(session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    @php
        $payload = $requestRecord->payload_json ?? [];
        $result = $requestRecord->result_json ?? [];
    @endphp

    <div class="row g-3">
        <div class="col-12 col-xl-5">
            <div class="card shadow-sm">
                <div class="card-header fw-semibold">Auditoria</div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-5">Estatus</dt><dd class="col-sm-7">{{ $requestRecord->status }}</dd>
                        <dt class="col-sm-5">CURP</dt><dd class="col-sm-7 font-monospace">{{ $requestRecord->curp_masked ?: 'N/D' }}</dd>
                        <dt class="col-sm-5">Hash</dt><dd class="col-sm-7 font-monospace">{{ $requestRecord->request_hash ?: 'N/D' }}</dd>
                        <dt class="col-sm-5">HTTP</dt><dd class="col-sm-7">{{ $requestRecord->response_code ?: 'N/D' }}</dd>
                        <dt class="col-sm-5">Recepcion</dt><dd class="col-sm-7">{{ optional($requestRecord->received_at)->format('Y-m-d H:i:s') ?: 'N/D' }}</dd>
                        <dt class="col-sm-5">Procesado</dt><dd class="col-sm-7">{{ optional($requestRecord->processed_at)->format('Y-m-d H:i:s') ?: 'N/D' }}</dd>
                        <dt class="col-sm-5">Total</dt><dd class="col-sm-7">{{ $requestRecord->total_count }}</dd>
                        <dt class="col-sm-5">Aceptados</dt><dd class="col-sm-7">{{ $requestRecord->accepted_count }}</dd>
                        <dt class="col-sm-5">Rechazados</dt><dd class="col-sm-7">{{ $requestRecord->rejected_count }}</dd>
                        <dt class="col-sm-5">Beneficiario</dt>
                        <dd class="col-sm-7">
                            @if($requestRecord->beneficiario_id)
                                <a href="{{ route('admin.beneficiarios.show', $requestRecord->beneficiario_id) }}">{{ $requestRecord->beneficiario_id }}</a>
                            @else
                                N/D
                            @endif
                        </dd>
                        <dt class="col-sm-5">Error</dt><dd class="col-sm-7">{{ $requestRecord->error_message ?: 'Ninguno' }}</dd>
                    </dl>
                </div>
                @if(in_array($requestRecord->status, [\App\Models\ApiTjInboundRequest::STATUS_FAILED, \App\Models\ApiTjInboundRequest::STATUS_ERROR], true))
                    <div class="card-footer">
                        <form method="POST" action="{{ route('admin.api-tj.requests.reprocess', $requestRecord) }}">
                            @csrf
                            <button type="submit" class="btn btn-primary" onclick="return confirm('Se reprocesara la solicitud guardada. ¿Deseas continuar?')">Reprocesar</button>
                        </form>
                    </div>
                @endif
            </div>
        </div>
        <div class="col-12 col-xl-7">
            <div class="card shadow-sm">
                <div class="card-header fw-semibold">Payload recibido</div>
                <div class="card-body">
                    <pre class="small mb-0">{{ json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                </div>
            </div>
            <div class="card shadow-sm mt-3">
                <div class="card-header fw-semibold">Resultado procesado</div>
                <div class="card-body">
                    <pre class="small mb-0">{{ json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
