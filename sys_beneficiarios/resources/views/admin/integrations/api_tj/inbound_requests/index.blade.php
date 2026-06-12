<x-app-layout>
    <x-slot name="header"><h2 class="h4 m-0">Integraciones API_TJ: Inbound requests</h2></x-slot>

    @include('admin.integrations.api_tj.partials.status-summary', ['statusSummary' => $statusSummary])

    <div class="card mb-3 shadow-sm">
        <div class="card-body">
            <div class="d-flex flex-column flex-lg-row justify-content-between gap-3">
                <form method="GET" class="row gy-2 gx-3 align-items-end flex-grow-1">
                    <div class="col-12 col-md-3">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="">Todos</option>
                            @foreach(['received', 'processing', 'accepted', 'rejected', 'failed', 'already_processed'] as $status)
                                <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ $status }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12 col-md-3">
                        <label class="form-label">Source</label>
                        <input type="text" name="source_system" value="{{ $filters['source_system'] ?? '' }}" class="form-control" placeholder="api_tj">
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label">Desde</label>
                        <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="form-control">
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label">Hasta</label>
                        <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="form-control">
                    </div>
                    <div class="col-12 col-md-2 d-flex gap-2">
                        <a href="{{ route('admin.integraciones.api_tj.inbound-requests.index') }}" class="btn btn-outline-secondary w-100">Limpiar</a>
                        <button type="submit" class="btn btn-primary w-100">Filtrar</button>
                    </div>
                </form>
                <div class="d-flex flex-column gap-2">
                    <a href="{{ route('admin.integraciones.api_tj.sync-runs.index') }}" class="btn btn-outline-info w-100">Ver corridas</a>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header">Requests inbound</div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-dark table-striped align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Recibido</th>
                            <th>Status</th>
                            <th>Source</th>
                            <th>External request</th>
                            <th>Operacion</th>
                            <th>Response code</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($requests as $requestRow)
                            <tr>
                                <td>{{ optional($requestRow->received_at)->format('Y-m-d H:i') }}</td>
                                <td>{{ $requestRow->status }}</td>
                                <td>{{ $requestRow->source_system }}</td>
                                <td><code>{{ $requestRow->external_request_id }}</code></td>
                                <td>{{ $requestRow->operation }}</td>
                                <td>{{ $requestRow->response_code ?? 'N/A' }}</td>
                                <td class="text-end">
                                    <a href="{{ route('admin.integraciones.api_tj.inbound-requests.show', $requestRow) }}" class="btn btn-sm btn-outline-light">Detalle</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-white-50 py-4">Sin requests inbound registrados.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($requests->hasPages())
            <div class="card-footer">
                {{ $requests->links() }}
            </div>
        @endif
    </div>
</x-app-layout>
