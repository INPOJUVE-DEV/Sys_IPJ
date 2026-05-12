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
                <div class="col-12 col-md-2">
                    <label class="form-label">Forma de registro</label>
                    <select name="source_system" class="form-select">
                        <option value="">Todos</option>
                        <option value="api_tj" @selected(($filters['source_system'] ?? '')==='api_tj')>Desde API_TJ</option>
                        <option value="manual" @selected(($filters['source_system'] ?? '')==='manual')>Captura manual</option>
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
            @if($beneficiarios->count())
                <div class="d-flex flex-column flex-md-row justify-content-between gap-2 text-white-50 small mb-3">
                    <div>Mostrando {{ $beneficiarios->firstItem() }} - {{ $beneficiarios->lastItem() }} de {{ $beneficiarios->total() }} registros</div>
                    <div class="text-uppercase">Bloques de 50 registros</div>
                </div>
            @endif
            <div class="beneficiarios-list">
                @forelse($beneficiarios as $b)
                    <a class="beneficiarios-list-item d-flex flex-column flex-md-row align-items-md-center gap-3 text-decoration-none" href="{{ route('admin.beneficiarios.show', $b) }}">
                        <div class="flex-grow-1">
                            <div class="fw-semibold text-white">{{ trim($b->nombre.' '.$b->apellido_paterno.' '.$b->apellido_materno) }}</div>
                            <div class="text-white-50 small d-flex align-items-center gap-1">
                                <i class="bi bi-geo-alt"></i>
                                <span>{{ optional($b->municipio)->nombre ?? 'Sin municipio' }}</span>
                            </div>
                        </div>
                        <div class="text-white text-md-end text-nowrap">
                            <div class="text-white-50 text-uppercase small">Forma de registro</div>
                            <div class="small">{{ $b->source_system === 'api_tj' ? 'Desde API_TJ' : 'Captura manual' }}</div>
                        </div>
                        <div class="text-white text-md-end text-nowrap">
                            <div class="text-white-50 text-uppercase small">Tarjeta</div>
                            <div class="fs-6 fw-semibold">{{ data_get($b, 'tarjeta.folio') ?? $b->folio_tarjeta ?? 'Sin tarjeta' }}</div>
                        </div>
                    </a>
                @empty
                    <div class="text-center text-muted py-4">Sin registros</div>
                @endforelse
            </div>
        </div>
        @if($beneficiarios->hasPages())
            <div class="card-footer">
                <div class="pagination-wrapper">
                    {{ $beneficiarios->links() }}
                </div>
            </div>
        @endif
    </div>
</x-app-layout>
