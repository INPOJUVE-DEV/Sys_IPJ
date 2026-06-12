<x-app-layout>
    <x-slot name="header"><h2 class="h4 m-0">Inbound request API_TJ</h2></x-slot>

    <div class="d-flex flex-wrap gap-2 mb-3">
        <a href="{{ route('admin.integraciones.api_tj.inbound-requests.index') }}" class="btn btn-outline-secondary">Volver a inbound requests</a>
        <a href="{{ route('admin.integraciones.api_tj.sync-runs.index') }}" class="btn btn-outline-info">Ver corridas</a>
    </div>

    <div class="row g-3">
        <div class="col-lg-5">
            <div class="card shadow-sm h-100">
                <div class="card-header">Metadatos</div>
                <div class="card-body d-grid gap-2">
                    <div><span class="text-white-50">Status:</span> <strong>{{ $inboundRequest->status }}</strong></div>
                    <div><span class="text-white-50">Source:</span> <strong>{{ $inboundRequest->source_system }}</strong></div>
                    <div><span class="text-white-50">External request:</span> <strong>{{ $inboundRequest->external_request_id }}</strong></div>
                    <div><span class="text-white-50">Operacion:</span> <strong>{{ $inboundRequest->operation }}</strong></div>
                    <div><span class="text-white-50">Recibido:</span> <strong>{{ optional($inboundRequest->received_at)->format('Y-m-d H:i:s') }}</strong></div>
                    <div><span class="text-white-50">Procesado:</span> <strong>{{ optional($inboundRequest->processed_at)->format('Y-m-d H:i:s') ?? 'N/A' }}</strong></div>
                    <div><span class="text-white-50">Response code:</span> <strong>{{ $inboundRequest->response_code ?? 'N/A' }}</strong></div>
                    <div><span class="text-white-50">Request hash:</span><br><code>{{ $inboundRequest->request_hash }}</code></div>
                    <div><span class="text-white-50">Payload cifrado:</span> <strong>{{ strlen((string) $inboundRequest->request_payload_encrypted) }} bytes</strong></div>
                    @if($inboundRequest->error_message)
                        <div class="alert alert-danger mb-0 mt-2">{{ $inboundRequest->error_message }}</div>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-lg-7">
            <div class="card shadow-sm h-100">
                <div class="card-header">Response body</div>
                <div class="card-body">
                    <pre class="mb-0 text-white small">{{ json_encode($inboundRequest->response_body ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
