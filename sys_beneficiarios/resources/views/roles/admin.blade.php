<x-app-layout>
    <x-slot name="header"><h2 class="h4 m-0">Panel de Administración</h2></x-slot>

    <div data-kpis-url="{{ route('admin.kpis', absolute: false) }}" data-export-url="{{ route('admin.beneficiarios.export', absolute: false) }}">
        <div class="card mb-3">
            <div class="card-body">
                <form id="kpiFilters" class="row gy-2 gx-3 align-items-end">
                    <div class="col-12 col-md-3">
                        <label class="form-label">Municipio</label>
                        <select name="municipio_id" class="form-select">
                            <option value="">—</option>
                            @foreach($municipios as $id=>$nombre)
                                <option value="{{ $id }}">{{ $nombre }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label">Seccional</label>
                        <input name="seccional" class="form-control">
                    </div>
                    <div class="col-12 col-md-3">
                        <label class="form-label">Capturista</label>
                        <select name="capturista" class="form-select">
                            <option value="">—</option>
                            @foreach($capturistas as $u)
                                <option value="{{ $u->uuid }}">{{ $u->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label">Desde</label>
                        <input type="date" name="from" class="form-control">
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label">Hasta</label>
                        <input type="date" name="to" class="form-control">
                    </div>
                    <div class="col-12 col-md-4 ms-auto text-end">
                        <a id="exportCsvBtn" href="#" class="btn btn-outline-success me-2">Exportar CSV</a>
                        <button class="btn btn-primary" type="submit">Aplicar</button>
                    </div>
                </form>
            </div>
        </div>
        <div class="row g-3 mb-3">
            <div class="col-sm-6 col-lg-3"><div class="card"><div class="card-body"><div class="text-muted">Total</div><div class="h3" id="kpiTotal">—</div></div></div></div>
            <div class="col-sm-6 col-lg-3"><div class="card"><div class="card-body"><div class="text-muted">Hoy</div><div class="h3" id="kpiTodayTotal">—</div></div></div></div>
            <div class="col-sm-6 col-lg-3"><div class="card"><div class="card-body"><div class="text-muted">Últimos 7 días</div><div class="h3" id="kpiWeekTotal">—</div></div></div></div>
            <div class="col-sm-6 col-lg-3"><div class="card"><div class="card-body"><div class="text-muted">Últimos 30 días</div><div class="h3" id="kpiLast30Total">—</div></div></div></div>
            <div class="col-sm-6 col-lg-3"><div class="card"><div class="card-body"><div class="text-muted">En rango de edad</div><div class="h3" id="kpiAgeRange">—</div><div class="small text-muted">18-28</div></div></div></div>
            <div class="col-sm-6 col-lg-3"><div class="card"><div class="card-body"><div class="text-muted">Préstamos Skate Plaza del mes</div><div class="h3" id="kpiSkatePlazaMonth">—</div></div></div></div>
        </div>

        @if(($apiTj['available'] ?? false))
            <div class="card mb-3 border-primary border-opacity-25 shadow-sm">
                @php
                    $registroLabels = [
                        'pending' => 'Pendiente',
                        'processed' => 'Atendido',
                        'failed' => 'Con observaciones',
                        'received' => 'Recibido',
                        'created' => 'Registrado',
                        'already_processed' => 'Ya revisado',
                        'rejected' => 'No procedio',
                        'conflict' => 'Requiere revision',
                        'error' => 'Con problema',
                    ];
                    $envioLabels = [
                        'running' => 'En proceso',
                        'success' => 'Completado',
                        'failed' => 'Con observaciones',
                        'error' => 'Con problema',
                    ];
                    $personaLabels = [
                        'pending_sync' => 'Listo para compartir',
                        'pending_data' => 'Pendiente por completar',
                        'synced' => 'Compartido correctamente',
                        'sync_failed' => 'Con observaciones',
                    ];
                @endphp
                <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
                    <div>
                        <div class="fw-semibold">Seguimiento con API_TJ</div>
                        <div class="small text-muted">Resumen de la informacion recibida y de lo que se ha compartido.</div>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <a href="{{ route('admin.api-tj.index') }}" class="btn btn-sm btn-primary">Resumen general</a>
                        <a href="{{ route('admin.api-tj.requests.index') }}" class="btn btn-sm btn-outline-primary">Registros recibidos</a>
                        <a href="{{ route('admin.api-tj.sync-runs.index') }}" class="btn btn-sm btn-outline-secondary">Registros enviados</a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row g-3 mb-3">
                        <div class="col-sm-6 col-xl-2"><div class="card h-100 bg-light border-0"><div class="card-body"><div class="text-muted small">Registros recibidos</div><div class="h3 mb-0">{{ $apiTj['inbound_total'] }}</div></div></div></div>
                        <div class="col-sm-6 col-xl-2"><div class="card h-100 bg-light border-0"><div class="card-body"><div class="text-muted small">Recibidas hoy</div><div class="h3 mb-0">{{ $apiTj['inbound_today'] }}</div></div></div></div>
                        <div class="col-sm-6 col-xl-2"><div class="card h-100 bg-light border-0"><div class="card-body"><div class="text-muted small">Ya atendidos</div><div class="h3 mb-0">{{ $apiTj['inbound_processed'] }}</div></div></div></div>
                        <div class="col-sm-6 col-xl-2"><div class="card h-100 bg-light border-0"><div class="card-body"><div class="text-muted small">Con observaciones</div><div class="h3 mb-0">{{ $apiTj['inbound_failed'] }}</div></div></div></div>
                        <div class="col-sm-6 col-xl-2"><div class="card h-100 bg-light border-0"><div class="card-body"><div class="text-muted small">Listos para compartir</div><div class="h3 mb-0">{{ $apiTj['pending_sync'] }}</div></div></div></div>
                        <div class="col-sm-6 col-xl-2"><div class="card h-100 bg-light border-0"><div class="card-body"><div class="text-muted small">Pendientes por completar</div><div class="h3 mb-0">{{ $apiTj['pending_data'] }}</div></div></div></div>
                    </div>

                    <div class="row g-3">
                        <div class="col-lg-7">
                            <div class="card h-100 border-0 bg-light">
                                <div class="card-header fw-semibold">Ultimos registros recibidos</div>
                                <div class="card-body p-0">
                                    <div class="table-responsive">
                                        <table class="table table-sm align-middle mb-0">
                                            <thead>
                                                <tr>
                                                    <th>Referencia</th>
                                                    <th>Situacion</th>
                                                    <th>Beneficiario</th>
                                                    <th>Tarjeta</th>
                                                    <th>Fecha</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @forelse($apiTj['recent_requests'] as $requestRecord)
                                                    <tr>
                                                        <td>
                                                            <a href="{{ route('admin.api-tj.requests.show', $requestRecord) }}" class="font-monospace text-decoration-none">
                                                                {{ $requestRecord->external_request_id }}
                                                            </a>
                                                        </td>
                                                        <td><span class="badge text-bg-secondary">{{ $registroLabels[$requestRecord->status] ?? $requestRecord->status }}</span></td>
                                                        <td class="small">
                                                            @if($requestRecord->beneficiario_id)
                                                                <a href="{{ route('admin.beneficiarios.show', $requestRecord->beneficiario_id) }}" class="text-decoration-none">
                                                                    {{ $requestRecord->beneficiario_id }}
                                                                </a>
                                                            @else
                                                                <span class="text-muted">Sin vincular</span>
                                                            @endif
                                                        </td>
                                                        <td class="font-monospace">{{ data_get($requestRecord, 'beneficiario.tarjeta.folio') ?? $requestRecord->beneficiario?->folio_tarjeta ?? 'N/D' }}</td>
                                                        <td class="small text-muted">{{ optional($requestRecord->received_at)->format('Y-m-d H:i') ?: 'N/D' }}</td>
                                                    </tr>
                                                @empty
                                                    <tr><td colspan="5" class="text-muted px-3 py-3">Sin solicitudes inbound registradas.</td></tr>
                                                @endforelse
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-5">
                            <div class="card h-100 border-0 bg-light">
                                <div class="card-header fw-semibold">Ultimos registros compartidos</div>
                                <div class="card-body p-0">
                                    <div class="table-responsive">
                                        <table class="table table-sm align-middle mb-0">
                                            <thead>
                                                <tr>
                                                    <th>Referencia</th>
                                                    <th>Situacion</th>
                                                    <th>Correctos</th>
                                                    <th>Con observaciones</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @forelse($apiTj['recent_sync_runs'] as $syncRun)
                                                    <tr>
                                                        <td>
                                                            <a href="{{ route('admin.api-tj.sync-runs.show', $syncRun) }}" class="font-monospace text-decoration-none">
                                                                {{ $syncRun->sync_id }}
                                                            </a>
                                                        </td>
                                                        <td><span class="badge text-bg-secondary">{{ $envioLabels[$syncRun->status] ?? $syncRun->status }}</span></td>
                                                        <td>{{ $syncRun->success_count }}</td>
                                                        <td>{{ $syncRun->failed_count }}</td>
                                                    </tr>
                                                @empty
                                                    <tr><td colspan="4" class="text-muted px-3 py-3">Sin ejecuciones de sync registradas.</td></tr>
                                                @endforelse
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card border-0 bg-light mt-3">
                        <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
                            <div class="fw-semibold">Personas registradas desde API_TJ</div>
                            <a href="{{ route('admin.beneficiarios.index', ['source_system' => 'api_tj']) }}" class="btn btn-sm btn-outline-primary">Ver todos</a>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-sm align-middle mb-0">
                                            <thead>
                                                <tr>
                                                    <th>Nombre</th>
                                                    <th>CURP</th>
                                                    <th>Tarjeta</th>
                                                    <th>Situacion de envio</th>
                                                    <th>Referencia de origen</th>
                                                    <th>Alta</th>
                                                </tr>
                                            </thead>
                                    <tbody>
                                        @forelse($apiTj['recent_api_beneficiarios'] as $beneficiario)
                                            <tr>
                                                <td>
                                                    <a href="{{ route('admin.beneficiarios.show', $beneficiario) }}" class="text-decoration-none">
                                                        {{ trim($beneficiario->nombre.' '.$beneficiario->apellido_paterno.' '.$beneficiario->apellido_materno) }}
                                                    </a>
                                                </td>
                                                <td class="font-monospace">{{ $beneficiario->curp }}</td>
                                                <td class="font-monospace">{{ data_get($beneficiario, 'tarjeta.folio') ?? $beneficiario->folio_tarjeta ?? 'N/D' }}</td>
                                                <td><span class="badge text-bg-secondary">{{ $personaLabels[$beneficiario->api_tj_sync_status] ?? ($beneficiario->api_tj_sync_status ?? 'Sin dato') }}</span></td>
                                                <td class="font-monospace">{{ $beneficiario->source_external_request_id ?? 'N/D' }}</td>
                                                <td class="small text-muted">{{ optional($beneficiario->created_at)->format('Y-m-d H:i') ?: 'N/D' }}</td>
                                            </tr>
                                        @empty
                                            <tr><td colspan="6" class="text-muted px-3 py-3">Sin beneficiarios creados por API_TJ.</td></tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <div class="row g-3">
            <div class="col-lg-6"><div class="card"><div class="card-header">Por municipio</div><div class="card-body"><canvas id="chartByMunicipio" height="180"></canvas></div></div></div>
            <div class="col-lg-6"><div class="card"><div class="card-header">Por seccional (Top 10)</div><div class="card-body"><canvas id="chartBySeccional" height="180"></canvas></div></div></div>
            <div class="col-lg-6"><div class="card"><div class="card-header">Por capturista (Top 10)</div><div class="card-body"><canvas id="chartByCapturista" height="180"></canvas></div></div></div>
            <div class="col-lg-6"><div class="card"><div class="card-header">Esta semana</div><div class="card-body"><canvas id="chartWeek" height="180"></canvas></div></div></div>
            <div class="col-lg-12"><div class="card"><div class="card-header">Préstamos Skate Plaza por mes</div><div class="card-body"><canvas id="chartSkatePlaza" height="180"></canvas></div></div></div>
            <div class="col-lg-12"><div class="card"><div class="card-header">Últimos 30 días</div><div class="card-body"><canvas id="chart30" height="200"></canvas></div></div></div>
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-header">Capturistas por semana</div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm table-striped align-middle mb-0" id="capturistasWeekTable">
                                <thead></thead>
                                <tbody>
                                    <tr>
                                        <td class="text-muted">Sin datos</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @vite(['resources/js/dashboard.js'])
</x-app-layout>
