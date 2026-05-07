<x-app-layout>
    <x-slot name="header">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
            <div>
                <h2 class="h4 m-0">Historial de sincronizaciones API_TJ</h2>
                <div class="text-muted small">Auditoria visual de los envios salientes de Sys_IPJ.</div>
            </div>
            <a href="{{ route('admin.api-tj.index') }}" class="btn btn-outline-primary">Volver al centro de control</a>
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
                        <th>Sync ID</th>
                        <th>Estatus</th>
                        <th>Actor</th>
                        <th>Inicio</th>
                        <th>Resultado</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($syncRuns as $syncRun)
                        <tr>
                            <td class="font-monospace">{{ $syncRun->sync_id }}</td>
                            <td><span class="badge text-bg-secondary">{{ $syncRun->status }}</span></td>
                            <td>{{ $syncRun->actor?->name ?: 'Sistema' }}</td>
                            <td>{{ optional($syncRun->started_at)->format('Y-m-d H:i') ?: 'N/D' }}</td>
                            <td>{{ $syncRun->success_count }}/{{ $syncRun->request_count }} correctos</td>
                            <td class="text-end">
                                <a href="{{ route('admin.api-tj.sync-runs.show', $syncRun) }}" class="btn btn-sm btn-outline-primary">Detalle</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-muted">Aun no hay sincronizaciones registradas.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($syncRuns->hasPages())
            <div class="card-footer">{{ $syncRuns->links() }}</div>
        @endif
    </div>
</x-app-layout>
