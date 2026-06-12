<x-app-layout>
    <x-slot name="header"><h2 class="h4 m-0">Integraciones API_TJ: Corridas</h2></x-slot>

    @include('admin.integrations.api_tj.partials.status-summary', ['statusSummary' => $statusSummary])

    <div class="card mb-3 shadow-sm">
        <div class="card-body">
            <div class="d-flex flex-column flex-lg-row justify-content-between gap-3">
                <form method="GET" class="row gy-2 gx-3 align-items-end flex-grow-1">
                    <div class="col-12 col-md-3">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="">Todos</option>
                            @foreach(['pending', 'queued', 'running', 'success', 'partial', 'failed', 'cancelled'] as $status)
                                <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ $status }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="form-label">Desde</label>
                        <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="form-control">
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="form-label">Hasta</label>
                        <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="form-control">
                    </div>
                    <div class="col-12 col-md-3 d-flex gap-2">
                        <a href="{{ route('admin.integraciones.api_tj.sync-runs.index') }}" class="btn btn-outline-secondary w-100">Limpiar</a>
                        <button type="submit" class="btn btn-primary w-100">Filtrar</button>
                    </div>
                </form>
                <div class="d-flex flex-column gap-2">
                    <form method="POST" action="{{ route('admin.integraciones.api_tj.cardholders.sync') }}">
                        @csrf
                        <button type="submit" class="btn btn-success w-100">Disparar sync manual</button>
                    </form>
                    <a href="{{ route('admin.integraciones.api_tj.inbound-requests.index') }}" class="btn btn-outline-info w-100">Ver inbound requests</a>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header">Historial de corridas</div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-dark table-striped align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Status</th>
                            <th>Operacion</th>
                            <th>Solicitada por</th>
                            <th class="text-end">Items</th>
                            <th class="text-end">Success</th>
                            <th class="text-end">Failed</th>
                            <th class="text-end">Skipped</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($runs as $run)
                            <tr>
                                <td>{{ optional($run->created_at)->format('Y-m-d H:i') }}</td>
                                <td><span class="badge bg-{{ in_array($run->status, ['success'], true) ? 'success' : (in_array($run->status, ['partial'], true) ? 'warning text-dark' : (in_array($run->status, ['failed'], true) ? 'danger' : 'secondary')) }}">{{ $run->status }}</span></td>
                                <td>{{ $run->operation }}</td>
                                <td>{{ $run->requestedBy?->name ?? $run->requested_by }}</td>
                                <td class="text-end">{{ $run->total_items }}</td>
                                <td class="text-end">{{ $run->success_count }}</td>
                                <td class="text-end">{{ $run->failed_count }}</td>
                                <td class="text-end">{{ $run->skipped_count }}</td>
                                <td class="text-end">
                                    <a href="{{ route('admin.integraciones.api_tj.sync-runs.show', $run) }}" class="btn btn-sm btn-outline-light">Detalle</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center text-white-50 py-4">Sin corridas registradas.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($runs->hasPages())
            <div class="card-footer">
                {{ $runs->links() }}
            </div>
        @endif
    </div>
</x-app-layout>
