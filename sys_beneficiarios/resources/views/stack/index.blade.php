<x-app-layout>
    <x-slot name="header">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
            <div>
                <h2 class="h3 m-0">Stack</h2>
                <div class="text-muted">Distribucion de tarjetas por region, municipio y capturista.</div>
            </div>
            <div class="d-flex flex-wrap gap-2">
                @if($isAdmin)
                    <a href="{{ $cardsRoute }}#agregar-tarjetas" class="btn btn-primary">
                        <i class="bi bi-plus-circle me-1"></i> Agregar tarjetas
                    </a>
                @endif
                <a href="{{ $cardsRoute }}#entregar-tarjetas" class="btn btn-outline-primary">
                    <i class="bi bi-person-check me-1"></i> Entregar a capturista
                </a>
            </div>
        </div>
    </x-slot>

    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small">Tarjetas totales</div>
                    <div class="display-6 fw-bold">{{ $global['total'] }}</div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small">En region</div>
                    <div class="display-6 fw-bold">{{ $global['region'] }}</div>
                </div>
            </div>
        </div>
        @if($isAdmin)
            <div class="col-sm-6 col-xl">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="text-muted small">Central</div>
                        <div class="display-6 fw-bold">{{ $global['central'] }}</div>
                    </div>
                </div>
            </div>
        @endif
        <div class="col-sm-6 col-xl">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small">Con capturistas</div>
                    <div class="display-6 fw-bold">{{ $global['capturistas'] }}</div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small">Capturadas</div>
                    <div class="display-6 fw-bold">{{ $global['capturadas'] }}</div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small">Incidencias</div>
                    <div class="display-6 fw-bold">{{ $global['incidencias'] }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-12 col-xl-8">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white">
                    <div class="fw-semibold">Tarjetas por region</div>
                    <div class="small text-muted">Vista rapida para saber donde esta el stock.</div>
                </div>
                <div class="card-body table-responsive">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Region</th>
                                <th class="text-end">En region</th>
                                <th class="text-end">Con capturistas</th>
                                <th class="text-end">Capturadas</th>
                                <th class="text-end">Incidencias</th>
                                <th class="text-end">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($officeRows as $row)
                                <tr>
                                    <td>
                                        <div class="fw-semibold">{{ $row['office']->nombre }}</div>
                                        <div class="small text-muted">{{ $row['office']->region ?: 'Sin region' }}</div>
                                    </td>
                                    <td class="text-end">{{ $row['region'] }}</td>
                                    <td class="text-end">{{ $row['capturistas'] }}</td>
                                    <td class="text-end">{{ $row['capturadas'] }}</td>
                                    <td class="text-end">{{ $row['incidencias'] }}</td>
                                    <td class="text-end h5 mb-0">{{ $row['total'] }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="text-muted">No hay regiones disponibles.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-4">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white">
                    <div class="fw-semibold">Acciones rapidas</div>
                    <div class="small text-muted">Botones pensados para operacion diaria.</div>
                </div>
                <div class="card-body d-grid gap-2">
                    @if($isAdmin)
                        <a href="{{ $cardsRoute }}#agregar-tarjetas" class="btn btn-primary btn-lg text-start">
                            <i class="bi bi-box-seam me-2"></i> Agregar tarjetas al stock
                        </a>
                        <a href="{{ $cardsRoute }}#mover-tarjetas" class="btn btn-outline-primary btn-lg text-start">
                            <i class="bi bi-arrow-left-right me-2"></i> Mover tarjetas a region
                        </a>
                    @endif
                    <a href="{{ $cardsRoute }}#entregar-tarjetas" class="btn btn-outline-primary btn-lg text-start">
                        <i class="bi bi-person-check me-2"></i> Entregar tarjetas a capturista
                    </a>
                    <a href="{{ $usersRoute }}" class="btn btn-outline-secondary btn-lg text-start">
                        <i class="bi bi-people me-2"></i> Usuarios de la region
                    </a>
                    <a href="{{ $valesRoute }}" class="btn btn-outline-secondary btn-lg text-start">
                        <i class="bi bi-journal-text me-2"></i> Vales internos
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-white">
            <div class="fw-semibold">Distribucion por municipio</div>
            <div class="small text-muted">Muestra cuantas tarjetas tiene cada region destinadas a cada municipio.</div>
        </div>
        <div class="card-body table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>Region</th>
                        <th>Municipio</th>
                        <th class="text-end">En region</th>
                        <th class="text-end">Con capturistas</th>
                        <th class="text-end">Capturadas</th>
                        <th class="text-end">Incidencias</th>
                        <th class="text-end">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($municipioRows as $row)
                        @php
                            $office = $officesById->get($row->oficina_id);
                            $municipio = $municipiosById->get($row->municipio_id);
                        @endphp
                        <tr>
                            <td>{{ $office?->nombre ?? 'Sin region' }}</td>
                            <td>{{ $municipio?->nombre ?? 'Sin municipio especifico' }}</td>
                            <td class="text-end">{{ (int) $row->en_region }}</td>
                            <td class="text-end">{{ (int) $row->con_capturistas }}</td>
                            <td class="text-end">{{ (int) $row->capturadas }}</td>
                            <td class="text-end">{{ (int) $row->incidencias }}</td>
                            <td class="text-end h5 mb-0">{{ (int) $row->total }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-muted">Aun no hay tarjetas asignadas a municipios.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
