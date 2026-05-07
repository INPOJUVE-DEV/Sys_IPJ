<x-app-layout>
    <x-slot name="header">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
            <div>
                <h2 class="h4 m-0">Centro de control API_TJ</h2>
                <div class="text-muted small">Panel grafico para QA, auditoria y sincronizacion de Tarjeta Joven.</div>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <form method="POST" action="{{ route('admin.api-tj.sync') }}">
                    @csrf
                    <button
                        type="submit"
                        class="btn btn-primary"
                        onclick="{{ $readiness['sync_ready'] ? "return confirm('Se enviara el padron minimo a API_TJ. Deseas continuar?')" : 'return false' }}"
                        @disabled(! $readiness['sync_ready'])
                    >
                        <i class="bi bi-arrow-repeat me-1"></i> Ejecutar sync
                    </button>
                </form>
                @if($readiness['sync_runs_ready'])
                    <a href="{{ route('admin.api-tj.sync-runs.index') }}" class="btn btn-outline-primary">
                        <i class="bi bi-clock-history me-1"></i> Historial de sync
                    </a>
                @else
                    <span class="btn btn-outline-primary disabled">
                        <i class="bi bi-clock-history me-1"></i> Historial de sync
                    </span>
                @endif
                @if($readiness['inbound_ready'])
                    <a href="{{ route('admin.api-tj.requests.index') }}" class="btn btn-outline-secondary">
                        <i class="bi bi-inbox me-1"></i> Solicitudes inbound
                    </a>
                @else
                    <span class="btn btn-outline-secondary disabled">
                        <i class="bi bi-inbox me-1"></i> Solicitudes inbound
                    </span>
                @endif
            </div>
        </div>
    </x-slot>

    @if(session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    @if($readiness['warning'])
        <div class="alert alert-warning">
            <strong>Configuracion pendiente.</strong>
            <div class="small">{{ $readiness['warning'] }}</div>
        </div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger">
            <strong>No se pudo ejecutar la prueba.</strong>
            <div class="small">{{ $errors->first() }}</div>
        </div>
    @endif

    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-2"><div class="card shadow-sm border-0"><div class="card-body"><div class="text-muted small">Pending sync</div><div class="h3 mb-0">{{ $summary['pending_sync'] }}</div></div></div></div>
        <div class="col-sm-6 col-xl-2"><div class="card shadow-sm border-0"><div class="card-body"><div class="text-muted small">Pending data</div><div class="h3 mb-0">{{ $summary['pending_data'] }}</div></div></div></div>
        <div class="col-sm-6 col-xl-2"><div class="card shadow-sm border-0"><div class="card-body"><div class="text-muted small">Synced</div><div class="h3 mb-0">{{ $summary['synced'] }}</div></div></div></div>
        <div class="col-sm-6 col-xl-2"><div class="card shadow-sm border-0"><div class="card-body"><div class="text-muted small">Sync failed</div><div class="h3 mb-0">{{ $summary['sync_failed'] }}</div></div></div></div>
        <div class="col-sm-6 col-xl-2"><div class="card shadow-sm border-0"><div class="card-body"><div class="text-muted small">Inbound OK</div><div class="h3 mb-0">{{ $summary['inbound_processed'] }}</div></div></div></div>
        <div class="col-sm-6 col-xl-2"><div class="card shadow-sm border-0"><div class="card-body"><div class="text-muted small">Inbound failed</div><div class="h3 mb-0">{{ $summary['inbound_failed'] }}</div></div></div></div>
    </div>

    <div class="row g-3">
        <div class="col-12 col-xxl-7">
            <div class="card shadow-sm h-100">
                <div class="card-header">
                    <div class="fw-semibold">Consola QA inbound</div>
                    <div class="small text-muted">Pega un lote JSON y ejecutalo desde la UI para validar el flujo sin usar herramientas externas.</div>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.api-tj.qa.inbound') }}">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label">Payload JSON</label>
                            <textarea
                                name="payload_json"
                                rows="18"
                                class="form-control font-monospace @error('payload_json') is-invalid @enderror"
                                @disabled(! $readiness['qa_inbound_ready'])
                            >{{ old('payload_json', json_encode($samplePayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) }}</textarea>
                            @error('payload_json')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <div class="form-text">La prueba interna reutiliza el mismo servicio del endpoint oficial `POST /api/api-tj/inbound`.</div>
                        </div>
                        <div class="d-flex flex-wrap gap-2">
                            <button type="submit" class="btn btn-primary" @disabled(! $readiness['qa_inbound_ready'])>
                                <i class="bi bi-play-circle me-1"></i> Ejecutar prueba inbound
                            </button>
                            @if($readiness['inbound_ready'])
                                <a href="{{ route('admin.api-tj.requests.index') }}" class="btn btn-outline-secondary">
                                    Ver auditoria completa
                                </a>
                            @else
                                <span class="btn btn-outline-secondary disabled">Ver auditoria completa</span>
                            @endif
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-12 col-xxl-5">
            <div class="card shadow-sm mb-3">
                <div class="card-header fw-semibold">Ultimas solicitudes inbound</div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Request ID</th>
                                    <th>Estatus</th>
                                    <th>Conteo</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($recentRequests as $requestRecord)
                                    <tr>
                                        <td class="font-monospace">{{ $requestRecord->external_request_id }}</td>
                                        <td><span class="badge text-bg-secondary">{{ $requestRecord->status }}</span></td>
                                        <td>{{ $requestRecord->accepted_count }}/{{ $requestRecord->total_count }}</td>
                                        <td class="text-end">
                                            <a href="{{ route('admin.api-tj.requests.show', $requestRecord) }}" class="btn btn-sm btn-outline-primary">Ver</a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="text-muted px-3 py-3">Sin solicitudes registradas.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-header fw-semibold">Ultimos syncs</div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Sync ID</th>
                                    <th>Estatus</th>
                                    <th>Enviados</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($recentSyncRuns as $syncRun)
                                    <tr>
                                        <td class="font-monospace">{{ $syncRun->sync_id }}</td>
                                        <td><span class="badge text-bg-secondary">{{ $syncRun->status }}</span></td>
                                        <td>{{ $syncRun->success_count }}/{{ $syncRun->request_count }}</td>
                                        <td class="text-end">
                                            <a href="{{ route('admin.api-tj.sync-runs.show', $syncRun) }}" class="btn btn-sm btn-outline-primary">Ver</a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="text-muted px-3 py-3">Sin ejecuciones registradas.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
