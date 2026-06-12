<x-app-layout>
    <x-slot name="header"><h2 class="h4 m-0">Corrida API_TJ</h2></x-slot>

    <div class="d-flex flex-wrap gap-2 mb-3">
        <a href="{{ route('admin.integraciones.api_tj.sync-runs.index') }}" class="btn btn-outline-secondary">Volver a corridas</a>
        <a href="{{ route('admin.integraciones.api_tj.inbound-requests.index') }}" class="btn btn-outline-info">Ver inbound requests</a>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-lg-4">
            <div class="card shadow-sm h-100">
                <div class="card-header">Resumen</div>
                <div class="card-body d-grid gap-2">
                    <div><span class="text-white-50">Status:</span> <strong>{{ $run->status }}</strong></div>
                    <div><span class="text-white-50">Operacion:</span> <strong>{{ $run->operation }}</strong></div>
                    <div><span class="text-white-50">Solicitada por:</span> <strong>{{ $run->requestedBy?->name ?? $run->requested_by }}</strong></div>
                    <div><span class="text-white-50">Creada:</span> <strong>{{ optional($run->created_at)->format('Y-m-d H:i:s') }}</strong></div>
                    <div><span class="text-white-50">Inicio:</span> <strong>{{ optional($run->started_at)->format('Y-m-d H:i:s') ?? 'N/A' }}</strong></div>
                    <div><span class="text-white-50">Fin:</span> <strong>{{ optional($run->finished_at)->format('Y-m-d H:i:s') ?? 'N/A' }}</strong></div>
                    @if($run->error_message)
                        <div class="alert alert-danger mb-0 mt-2">{{ $run->error_message }}</div>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-lg-8">
            <div class="card shadow-sm h-100">
                <div class="card-header">Contadores</div>
                <div class="card-body">
                    <div class="row text-center g-3">
                        <div class="col-6 col-md-3"><div class="border rounded p-3"><div class="text-white-50 small">Total</div><div class="fs-4 fw-semibold">{{ $run->total_items }}</div></div></div>
                        <div class="col-6 col-md-3"><div class="border rounded p-3"><div class="text-white-50 small">Success</div><div class="fs-4 fw-semibold">{{ $run->success_count }}</div></div></div>
                        <div class="col-6 col-md-3"><div class="border rounded p-3"><div class="text-white-50 small">Failed</div><div class="fs-4 fw-semibold">{{ $run->failed_count }}</div></div></div>
                        <div class="col-6 col-md-3"><div class="border rounded p-3"><div class="text-white-50 small">Skipped</div><div class="fs-4 fw-semibold">{{ $run->skipped_count }}</div></div></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header">Items</div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-dark table-striped align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Beneficiario</th>
                            <th>Status</th>
                            <th>Response code</th>
                            <th>Payload hash</th>
                            <th>Error</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($run->items as $item)
                            <tr>
                                <td>
                                    @if($item->beneficiario)
                                        <div class="fw-semibold">{{ trim($item->beneficiario->nombre.' '.$item->beneficiario->apellido_paterno.' '.$item->beneficiario->apellido_materno) }}</div>
                                        <div class="text-white-50 small">{{ $item->beneficiario->curp }}</div>
                                    @else
                                        <span class="text-white-50">Beneficiario no disponible</span>
                                    @endif
                                </td>
                                <td>{{ $item->status }}</td>
                                <td>{{ $item->response_code ?? 'N/A' }}</td>
                                <td><code>{{ $item->payload_hash }}</code></td>
                                <td>{{ $item->error_message ?? 'N/A' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-white-50 py-4">Esta corrida no tiene items.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
