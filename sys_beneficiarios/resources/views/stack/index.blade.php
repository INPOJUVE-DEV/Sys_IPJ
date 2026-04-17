<x-app-layout>
    <x-slot name="header">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
            <div>
                <h2 class="h3 m-0">Stack</h2>
                <div class="text-muted">Distribucion de tarjetas por region y municipio.</div>
            </div>
            <div class="d-flex flex-wrap gap-2">
                @if($isAdmin)
                    <a href="{{ $cardsRoute }}#agregar-tarjetas" class="btn btn-primary">
                        <i class="bi bi-plus-circle me-1"></i> Agregar tarjetas
                    </a>
                @endif
                <a href="{{ $cardsRoute }}#entregar-tarjetas" class="btn btn-outline-primary">
                    <i class="bi bi-geo-alt me-1"></i> Asignar a municipio
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
                    <div class="text-muted small">En oficina</div>
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
                    <div class="text-muted small">Asignadas a municipio</div>
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
    </div>

    <div class="row g-3 mb-4">
        <div class="col-12">
            <div class="card shadow-sm h-100">
                <div class="card-header">
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
                        <i class="bi bi-geo-alt me-2"></i> Asignar tarjetas a municipio
                    </a>
                    <a href="{{ $usersRoute }}" class="btn btn-outline-secondary btn-lg text-start">
                        <i class="bi bi-people me-2"></i> Usuarios de la region
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header">
            <div class="fw-semibold">Distribucion por municipio</div>
            <div class="small text-muted">Municipios con tarjetas asignadas y cuantas ya fueron capturadas.</div>
        </div>
        <div class="card-body table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>Region</th>
                        <th>Municipio</th>
                        <th class="text-end">Tarjetas Asignadas</th>
                        <th class="text-end">Tarjetas capturadas</th>
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
                            <td class="text-end h5 mb-0">{{ (int) $row->asignadas }}</td>
                            <td class="text-end h5 mb-0">{{ (int) $row->capturadas }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-muted">Aun no hay tarjetas asignadas a municipios.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
