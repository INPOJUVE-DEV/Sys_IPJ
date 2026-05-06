<x-app-layout>
    <x-slot name="header">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
            <div>
                <h2 class="h4 m-0">Solicitudes recibidas de API_TJ</h2>
                <div class="text-muted small">Auditoria de expedientes entrantes y errores de procesamiento.</div>
            </div>
            <a href="{{ route('admin.inventario.tarjetas.index') }}" class="btn btn-outline-primary">Volver a tarjetas</a>
        </div>
    </x-slot>

    @if(session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div class="card shadow-sm">
        <div class="card-body table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>Request ID</th>
                        <th>CURP</th>
                        <th>Estatus</th>
                        <th>Recepcion</th>
                        <th>Beneficiario</th>
                        <th>Error</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($requests as $requestRecord)
                        @php $payload = $requestRecord->payload_json ?? []; @endphp
                        <tr>
                            <td class="font-monospace">{{ $requestRecord->external_request_id }}</td>
                            <td class="font-monospace">{{ $requestRecord->curp_masked ?: 'N/D' }}</td>
                            <td><span class="badge text-bg-secondary">{{ $requestRecord->status }}</span></td>
                            <td>{{ optional($requestRecord->received_at)->format('Y-m-d H:i') ?: 'N/D' }}</td>
                            <td>
                                @if($requestRecord->beneficiario_id)
                                    <a href="{{ route('admin.beneficiarios.show', $requestRecord->beneficiario_id) }}">
                                        {{ data_get($payload, 'beneficiario.nombre', 'Ver beneficiario') }}
                                    </a>
                                @else
                                    {{ trim(data_get($payload, 'beneficiario.nombre', '').' '.data_get($payload, 'beneficiario.apellido_paterno', '')) ?: 'N/D' }}
                                @endif
                            </td>
                            <td class="small text-muted">{{ \Illuminate\Support\Str::limit($requestRecord->error_message, 120) }}</td>
                            <td class="text-end">
                                <a href="{{ route('admin.api-tj.requests.show', $requestRecord) }}" class="btn btn-sm btn-outline-primary">Detalle</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-muted">Aun no hay solicitudes registradas.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($requests->hasPages())
            <div class="card-footer">{{ $requests->links() }}</div>
        @endif
    </div>
</x-app-layout>
