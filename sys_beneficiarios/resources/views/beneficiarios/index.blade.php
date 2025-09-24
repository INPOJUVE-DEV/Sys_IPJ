<x-app-layout>
    <x-slot name="header">
        <div class="d-flex align-items-center justify-content-between">
            <h2 class="h4 m-0">Beneficiarios</h2>
            <a href="{{ route('beneficiarios.create') }}" class="btn btn-primary">Nuevo</a>
        </div>
    </x-slot>

    @if(session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div class="card mb-3">
        <div class="card-body">
            <form class="row gy-2 gx-3 align-items-end" method="GET">
                <div class="col-12 col-md-3">
                    <label class="form-label">Búsqueda</label>
                    <input type="text" name="q" value="{{ $q }}" class="form-control" placeholder="Folio, CURP o nombre">
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label">Municipio</label>
                    <select name="municipio_id" class="form-select">
                        <option value="">—</option>
                        @foreach($municipios as $id=>$nombre)
                            <option value="{{ $id }}" @selected(($filters['municipio_id'] ?? '')==$id)>{{ $nombre }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label">Seccional</label>
                    <input name="seccional" value="{{ $filters['seccional'] ?? '' }}" class="form-control">
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label">Distrito local</label>
                    <input name="distrito_local" value="{{ $filters['distrito_local'] ?? '' }}" class="form-control">
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label">Distrito federal</label>
                    <input name="distrito_federal" value="{{ $filters['distrito_federal'] ?? '' }}" class="form-control">
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label">Sexo</label>
                    <select name="sexo" class="form-select">
                        <option value="">—</option>
                        @foreach(['M'=>'M','F'=>'F','X'=>'X'] as $key=>$val)
                            <option value="{{ $key }}" @selected(($filters['sexo'] ?? '')===$key)>{{ $val }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label">Discapacidad</label>
                    <select name="discapacidad" class="form-select">
                        <option value="">—</option>
                        <option value="1" @selected(($filters['discapacidad'] ?? '')==='1')>Sí</option>
                        <option value="0" @selected(($filters['discapacidad'] ?? '')==='0')>No</option>
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label">Borrador</label>
                    <select name="is_draft" class="form-select">
                        <option value="">—</option>
                        <option value="1" @selected(($filters['is_draft'] ?? '')==='1')>Sí</option>
                        <option value="0" @selected(($filters['is_draft'] ?? '')==='0')>No</option>
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label">Edad mín</label>
                    <input type="number" name="edad_min" value="{{ $filters['edad_min'] ?? '' }}" class="form-control">
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label">Edad máx</label>
                    <input type="number" name="edad_max" value="{{ $filters['edad_max'] ?? '' }}" class="form-control">
                </div>
                <div class="col-12 col-md-3 ms-auto text-end">
                    <a href="{{ route('beneficiarios.index') }}" class="btn btn-outline-secondary me-2"><i class="bi bi-arrow-counterclockwise me-1"></i>Limpiar</a>
                    <button class="btn btn-primary" type="submit"><i class="bi bi-funnel me-1"></i>Filtrar</button>
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
                                        <span class="badge bg-secondary text-white">{{ $b->curp }}</span>
                                    </div>
                                    <div class="fw-semibold">{{ $b->nombre }} {{ $b->apellido_paterno }} {{ $b->apellido_materno }}</div>
                                </div>
                                <div class="small text-white-50 d-flex flex-column gap-1">
                                    <div><i class="bi bi-geo-alt me-1"></i>{{ optional($b->municipio)->nombre ?? 'Sin municipio' }}</div>
                                    <div><i class="bi bi-diagram-3 me-1"></i>Seccional {{ $b->seccional ?? 'N/D' }}</div>
                                    <div><i class="bi bi-person-badge me-1"></i>{{ optional($b->creador)->name ?? 'Sin capturista' }}</div>
                                </div>
                                <div class="mt-auto d-flex flex-column gap-2">
                                    <a class="btn btn-outline-secondary btn-sm w-100" href="{{ route('beneficiarios.edit', $b) }}">
                                        <i class="bi bi-pencil-square me-1"></i>Editar
                                    </a>
                                    <form action="{{ route('beneficiarios.destroy', $b) }}" method="POST" class="m-0" onsubmit="return confirm('¿Eliminar beneficiario?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-outline-danger btn-sm w-100">
                                            <i class="bi bi-trash me-1"></i>Eliminar
                                        </button>
                                    </form>
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
