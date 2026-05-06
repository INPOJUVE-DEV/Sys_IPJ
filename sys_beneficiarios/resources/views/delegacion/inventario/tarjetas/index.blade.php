<x-app-layout>
    <x-slot name="header">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
            <div>
                <h2 class="h4 m-0">Tarjetas de mi region</h2>
                <div class="text-muted small">Distribuye cantidades por municipio y sigue el avance de captura.</div>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <form method="POST" action="{{ route('delegacion.api-tj.sync') }}">
                    @csrf
                    <button type="submit" class="btn btn-primary" onclick="return confirm('Se enviara el padron minimo a API_TJ. ¿Deseas continuar?')">
                        <i class="bi bi-arrow-repeat me-1"></i> Sincronizar con app
                    </button>
                </form>
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
            <div class="small">Revisa la cantidad o municipio seleccionado.</div>
        </div>
    @endif

    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl"><div class="card border-0 shadow-sm"><div class="card-body"><div class="text-muted small">Total region</div><div class="h3 mb-0">{{ $summary['oficina'] }}</div></div></div></div>
        <div class="col-sm-6 col-xl"><div class="card border-0 shadow-sm"><div class="card-body"><div class="text-muted small">Listas para distribuir</div><div class="h3 mb-0">{{ $summary['pendientes'] }}</div></div></div></div>
        <div class="col-sm-6 col-xl"><div class="card border-0 shadow-sm"><div class="card-body"><div class="text-muted small">Asignadas a municipio</div><div class="h3 mb-0">{{ $summary['usuario'] }}</div></div></div></div>
        <div class="col-sm-6 col-xl"><div class="card border-0 shadow-sm"><div class="card-body"><div class="text-muted small">Beneficiarios capturados</div><div class="h3 mb-0">{{ $summary['consumida'] }}</div></div></div></div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-12 col-lg-5" id="entregar-tarjetas">
            <div class="card shadow-sm h-100">
                <div class="card-header">
                    <div class="fw-semibold">Asignar tarjetas a municipio</div>
                    <div class="small text-muted">Elige cantidad y municipio. La captura del numero fisico se hace despues en beneficiarios.</div>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('delegacion.inventario.tarjetas.assignRange') }}" class="row g-3">
                        @csrf
                        <div class="col-md-5">
                            <label class="form-label">Cantidad</label>
                            <input name="cantidad" type="number" min="1" class="form-control form-control-lg" value="{{ old('cantidad') }}" required>
                        </div>
                        <div class="col-md-7">
                            <label class="form-label">Municipio a trabajar</label>
                            <select name="municipio_id" class="form-select form-select-lg" required>
                                <option value="" disabled @selected(! old('municipio_id'))>Selecciona un municipio</option>
                                @foreach($municipios as $municipio)
                                    <option value="{{ $municipio->id }}" @selected((string) old('municipio_id') === (string) $municipio->id)>{{ $municipio->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Nota interna</label>
                            <input name="observaciones" class="form-control" value="{{ old('observaciones') }}" placeholder="Ej. ruta del dia">
                        </div>
                        <div class="col-12 d-grid">
                            <button class="btn btn-primary btn-lg" type="submit">Asignar tarjetas</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-12 col-lg-7">
            <div class="card shadow-sm h-100">
                <div class="card-header">
                    <div class="fw-semibold">Buscar en mi region</div>
                    <div class="small text-muted">Filtra el resumen para revisar entregas.</div>
                </div>
                <div class="card-body">
                    <form method="GET" class="row g-2">
                        <div class="col-md-6">
                            <select name="municipio_id" class="form-select">
                                <option value="">Todos los municipios</option>
                                @foreach($municipios as $municipio)
                                    <option value="{{ $municipio->id }}" @selected(($filters['municipio_id'] ?? '') == $municipio->id)>{{ $municipio->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12 d-grid d-md-flex justify-content-md-end">
                            <button class="btn btn-outline-secondary" type="submit">Ver resultado</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header">
            <div class="fw-semibold">Resumen del stock</div>
            <div class="small text-muted">Tu delegacion y el detalle por municipio en un solo dashboard.</div>
        </div>
        <div class="card-body">
            <div class="accordion" id="stock-dashboard-delegacion">
                <div class="accordion-item border rounded-3 overflow-hidden">
                    <h2 class="accordion-header" id="heading-oficina-{{ $officeDashboard->id }}">
                        <button
                            class="accordion-button"
                            type="button"
                            data-bs-toggle="collapse"
                            data-bs-target="#oficina-{{ $officeDashboard->id }}"
                            aria-expanded="true"
                            aria-controls="oficina-{{ $officeDashboard->id }}"
                        >
                            <div class="w-100 pe-3">
                                <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3">
                                    <div>
                                        <div class="fw-semibold">{{ $officeDashboard->nombre }}</div>
                                        <div class="small text-muted">{{ $officeDashboard->region ?: 'Sin region' }}</div>
                                    </div>
                                    <div class="d-flex flex-wrap gap-3 small">
                                        <span><strong>{{ $officeDashboard->asignadas }}</strong> asignadas</span>
                                        <span><strong>{{ $officeDashboard->capturadas }}</strong> capturadas</span>
                                        <span><strong>{{ $officeDashboard->pendientes }}</strong> pendientes</span>
                                    </div>
                                </div>
                            </div>
                        </button>
                    </h2>
                    <div
                        id="oficina-{{ $officeDashboard->id }}"
                        class="accordion-collapse collapse show"
                        aria-labelledby="heading-oficina-{{ $officeDashboard->id }}"
                        data-bs-parent="#stock-dashboard-delegacion"
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
                                        @forelse($officeDashboard->municipios as $municipio)
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
                                                <td colspan="4" class="text-muted">Aun no hay municipios con tarjetas o capturas en esta delegacion.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
