<x-app-layout>
    <x-slot name="header"><h2 class="h4 m-0">Beneficiarios (Admin)</h2></x-slot>

    <div class="card mb-3">
        <div class="card-body">
            <form class="row gy-2 gx-3 align-items-end" method="GET">
                <div class="col-12 col-md-3">
                    <label class="form-label">Municipio</label>
                    <select name="municipio_id" class="form-select">
                        <option value="">â€”</option>
                        @foreach($municipios as $id=>$nombre)
                            <option value="{{ $id }}" @selected(($filters['municipio_id'] ?? '')==$id)>{{ $nombre }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label">Seccional</label>
                    <input name="seccional" value="{{ $filters['seccional'] ?? '' }}" class="form-control">
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label">Capturista</label>
                    <select name="capturista" class="form-select">
                        <option value="">â€”</option>
                        @foreach($capturistas as $u)
                            <option value="{{ $u->uuid }}" @selected(($filters['capturista'] ?? '')==$u->uuid)>{{ $u->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label">Desde</label>
                    <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="form-control">
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label">Hasta</label>
                    <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="form-control">
                </div>
                <div class="col-12 col-md-4 ms-auto text-end">
                    <a href="{{ route('admin.beneficiarios.index') }}" class="btn btn-outline-secondary me-2">Limpiar</a>
                    <a class="btn btn-outline-success me-2" href="{{ route('admin.beneficiarios.export', request()->query()) }}">Exportar CSV</a>
                    <button class="btn btn-primary" type="submit">Filtrar</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-3">
                @forelse($beneficiarios as $b)
                    <div class="col">
                        <div class="card bg-dark border border-white text-white h-100 shadow-sm">
                            <div class="card-body d-flex flex-column gap-3">
                                <div>
                                    <div class="d-flex justify-content-between align-items-start gap-2">
                                        <div>
                                            <span class="text-white-50 small text-uppercase">Folio</span>
                                            <div class="h6 text-white mb-0">{{ $b->folio_tarjeta }}</div>
                                        </div>
                                        <span class="badge bg-secondary text-white">Registrado</span>
                                    </div>
                                    <div class="fw-semibold">{{ $b->nombre }} {{ $b->apellido_paterno }} {{ $b->apellido_materno }}</div>
                                </div>
                                <div class="small text-white-50 d-flex flex-column gap-1">
                                    <div><i class="bi bi-geo-alt me-1"></i>{{ optional($b->municipio)->nombre ?? 'Sin municipio' }}</div>
                                    <div><i class="bi bi-diagram-3 me-1"></i>Seccional {{ $b->seccional ?? 'N/D' }}</div>
                                    <div><i class="bi bi-person-badge me-1"></i>{{ optional($b->creador)->name ?? 'Sin capturista' }}</div>
                                </div>
                                <div class="mt-auto">
                                    <a class="btn btn-outline-light btn-sm w-100" href="{{ route('admin.beneficiarios.show', $b) }}">
                                        <i class="bi bi-eye me-1"></i>Ver detalles
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="col-12">
                        <div class="text-center text-muted py-4">Sin registros</div>
                    </div>
                @endforelse
            </div>
        </div>
        @if($beneficiarios->hasPages())
            <div class="card-footer">{{ $beneficiarios->links() }}</div>
        @endif
    </div>
</x-app-layout>

