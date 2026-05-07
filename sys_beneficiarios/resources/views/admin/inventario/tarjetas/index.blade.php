<x-app-layout>
    <x-slot name="header">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
            <div>
                <h2 class="h4 m-0">Inventario de tarjetas</h2>
                <div class="text-muted small">Control de cantidades por central, delegacion y municipio.</div>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <form method="POST" action="{{ route('admin.api-tj.sync') }}">
                    @csrf
                    <button type="submit" class="btn btn-primary" onclick="return confirm('Se enviara el padron minimo a API_TJ. ¿Deseas continuar?')">
                        <i class="bi bi-arrow-repeat me-1"></i> Sincronizar con app
                    </button>
                </form>
                <a href="{{ route('admin.api-tj.index') }}" class="btn btn-outline-secondary">
                    <i class="bi bi-window-sidebar me-1"></i> Centro API_TJ
                </a>
                <a href="{{ route('stack.index') }}" class="btn btn-outline-primary">
                    <i class="bi bi-bar-chart-line me-1"></i> Ver Stack
                </a>
            </div>
        </div>
    </x-slot>

    @if(session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger">
            <strong>No se pudo guardar.</strong>
            <div class="small">Revisa la cantidad, region o municipio seleccionado.</div>
        </div>
    @endif

    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-2"><div class="card border-0 shadow-sm"><div class="card-body"><div class="text-muted small">Total</div><div class="h3 mb-0">{{ $summary['total'] }}</div></div></div></div>
        <div class="col-sm-6 col-xl-2"><div class="card border-0 shadow-sm"><div class="card-body"><div class="text-muted small">Central</div><div class="h3 mb-0">{{ $summary['central'] }}</div></div></div></div>
        <div class="col-sm-6 col-xl-2"><div class="card border-0 shadow-sm"><div class="card-body"><div class="text-muted small">En delegaciones</div><div class="h3 mb-0">{{ $summary['delegacion'] }}</div></div></div></div>
        <div class="col-sm-6 col-xl-2"><div class="card border-0 shadow-sm"><div class="card-body"><div class="text-muted small">En municipios</div><div class="h3 mb-0">{{ $summary['municipio'] }}</div></div></div></div>
        <div class="col-sm-6 col-xl-2"><div class="card border-0 shadow-sm"><div class="card-body"><div class="text-muted small">Beneficiarios capturados</div><div class="h3 mb-0">{{ $summary['beneficiarios'] }}</div></div></div></div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-12 col-xl-6" id="agregar-tarjetas">
            <div class="card shadow-sm h-100">
                <div class="card-header">
                    <div class="fw-semibold">Agregar tarjetas</div>
                    <div class="small text-muted">Las altas nuevas siempre entran primero a oficina central.</div>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.inventario.tarjetas.storeRange') }}" class="row g-3">
                        @csrf
                        <div class="col-md-6">
                            <label class="form-label">Cantidad</label>
                            <input name="cantidad" type="number" min="1" class="form-control form-control-lg" value="{{ old('cantidad') }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Destino inicial</label>
                            <input class="form-control form-control-lg" value="Oficina central" disabled>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Nota interna</label>
                            <input name="observaciones" class="form-control" value="{{ old('observaciones') }}" placeholder="Ej. entrega inicial">
                        </div>
                        <div class="col-12 d-grid">
                            <button class="btn btn-primary btn-lg" type="submit">Agregar al stock</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-6" id="mover-tarjetas">
            <div class="card shadow-sm h-100">
                <div class="card-header">
                    <div class="fw-semibold">Distribuir a delegacion</div>
                    <div class="small text-muted">Desde oficina central hacia cada delegacion.</div>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.inventario.tarjetas.transferRange') }}" class="row g-3">
                        @csrf
                        <div class="col-md-4">
                            <label class="form-label">Cantidad</label>
                            <input name="cantidad" type="number" min="1" class="form-control form-control-lg" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Origen</label>
                            <input class="form-control form-control-lg" value="Oficina central" disabled>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Enviar a delegacion</label>
                            <select name="destino_oficina_id" class="form-select form-select-lg" required>
                                @foreach($delegaciones as $office)
                                    <option value="{{ $office->id }}">{{ $office->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Nota interna</label>
                            <input name="observaciones" class="form-control" placeholder="Ej. reparto semanal">
                        </div>
                        <div class="col-12 d-grid">
                            <button class="btn btn-outline-primary btn-lg" type="submit">Mover tarjetas</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header">
            <div class="fw-semibold">Resumen del stock</div>
            <div class="small text-muted">Selecciona una delegacion para ver sus municipios y el avance de captura.</div>
        </div>
        <div class="card-body">
            <form method="GET" class="row g-2 mb-3">
                <div class="col-md-4">
                    <select name="oficina_id" class="form-select">
                        <option value="">Todas las delegaciones</option>
                        @foreach($delegaciones as $office)
                            <option value="{{ $office->id }}" @selected(($filters['oficina_id'] ?? '') == $office->id)>{{ $office->nombre }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <select name="municipio_id" class="form-select">
                        <option value="">Todos los municipios</option>
                        @foreach($municipios as $municipio)
                            <option value="{{ $municipio->id }}" @selected(($filters['municipio_id'] ?? '') == $municipio->id)>{{ $municipio->nombre }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <button class="btn btn-outline-secondary w-100" type="submit">Ver resultado</button>
                </div>
            </form>

            <div class="accordion" id="stock-dashboard-admin">
                @forelse($officeDashboards as $office)
                    @php($collapseId = 'delegacion-'.$office->id)
                    <div class="accordion-item border rounded-3 overflow-hidden mb-3">
                        <h2 class="accordion-header" id="heading-{{ $office->id }}">
                            <button
                                class="accordion-button @if(! $loop->first) collapsed @endif"
                                type="button"
                                data-bs-toggle="collapse"
                                data-bs-target="#{{ $collapseId }}"
                                aria-expanded="{{ $loop->first ? 'true' : 'false' }}"
                                aria-controls="{{ $collapseId }}"
                            >
                                <div class="w-100 pe-3">
                                    <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3">
                                        <div>
                                            <div class="fw-semibold">{{ $office->nombre }}</div>
                                            <div class="small text-muted">{{ $office->region ?: 'Sin region' }}</div>
                                        </div>
                                        <div class="d-flex flex-wrap gap-3 small">
                                            <span><strong>{{ $office->asignadas }}</strong> asignadas</span>
                                            <span><strong>{{ $office->capturadas }}</strong> capturadas</span>
                                            <span><strong>{{ $office->pendientes }}</strong> pendientes</span>
                                        </div>
                                    </div>
                                </div>
                            </button>
                        </h2>
                        <div
                            id="{{ $collapseId }}"
                            class="accordion-collapse collapse @if($loop->first) show @endif"
                            aria-labelledby="heading-{{ $office->id }}"
                            data-bs-parent="#stock-dashboard-admin"
                        >
                            <div class="accordion-body">
                                <div class="table-responsive">
                                    <table class="table align-middle mb-0">
                                        <thead>
                                            <tr>
                                                <th>Municipio</th>
                                                <th class="text-end">Tarjetas asignadas</th>
                                                <th class="text-end">Capturadas</th>
                                                <th class="text-end">Pendientes</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse($office->municipios as $municipio)
                                                <tr>
                                                    <td>
                                                        <div class="fw-semibold">{{ $municipio->nombre }}</div>
                                                        <div class="small text-muted">{{ $municipio->region ?: 'Sin region' }}</div>
                                                    </td>
                                                    <td class="text-end">{{ $municipio->asignadas }}</td>
                                                    <td class="text-end">{{ $municipio->capturadas }}</td>
                                                    <td class="text-end">{{ $municipio->pendientes }}</td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="4" class="text-muted">No hay municipios con tarjetas o capturas en esta delegacion.</td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="text-muted">Aun no hay delegaciones con tarjetas registradas.</div>
                @endforelse
            </div>
        </div>
    </div>
</x-app-layout>
