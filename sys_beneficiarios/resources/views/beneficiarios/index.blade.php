<x-app-layout>
    <x-slot name="header">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
            <div>
                <h2 class="h4 m-0">Beneficiarios</h2>
                <div class="text-muted small">Busca por CURP o numero de tarjeta y edita los registros encontrados.</div>
            </div>
            <div class="d-flex flex-wrap gap-2">
                @php
                    $syncRoute = auth()->user()?->hasRole('admin')
                        ? route('admin.api-tj.sync')
                        : (auth()->user()?->hasRole('delegado') ? route('delegacion.api-tj.sync') : null);
                @endphp
                @if($syncRoute)
                    <form method="POST" action="{{ $syncRoute }}">
                        @csrf
                        <button class="btn btn-outline-primary" type="submit">
                            <i class="bi bi-arrow-repeat me-1"></i>Sincronizar con app
                        </button>
                    </form>
                    @if(auth()->user()?->hasRole('admin'))
                        <a href="{{ route('admin.api-tj.index') }}" class="btn btn-outline-secondary">
                            <i class="bi bi-window-sidebar me-1"></i>API TJ
                        </a>
                    @endif
                @endif
                <a href="{{ route('beneficiarios.create') }}" class="btn btn-primary">
                    <i class="bi bi-plus-circle me-1"></i>Nuevo
                </a>
            </div>
        </div>
    </x-slot>

    @if(session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div class="card mb-3">
        <div class="card-body">
            <form class="row gy-2 gx-3 align-items-end" method="GET">
                <div class="col-12 col-lg-8">
                    <label class="form-label">Busqueda</label>
                    <input type="text" name="q" value="{{ $q }}" class="form-control" placeholder="CURP o numero de tarjeta">
                    <div class="form-text">La busqueda filtra coincidencias por CURP completa o numero de tarjeta.</div>
                </div>
                <div class="col-12 col-lg-4 text-lg-end">
                    <a href="{{ route('beneficiarios.index') }}" class="btn btn-outline-secondary me-2">
                        <i class="bi bi-arrow-counterclockwise me-1"></i>Limpiar
                    </a>
                    <button class="btn btn-primary" type="submit">
                        <i class="bi bi-search me-1"></i>Buscar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            @if($beneficiarios->count())
                <div class="text-muted small mb-3">
                    Mostrando {{ $beneficiarios->firstItem() }} - {{ $beneficiarios->lastItem() }} de {{ $beneficiarios->total() }} beneficiarios.
                </div>
            @endif
            <div class="row row-cols-1 row-cols-xl-2 g-3">
                @forelse($beneficiarios as $b)
                    <div class="col">
                        <div class="border rounded-3 p-3 h-100 d-flex flex-column gap-3">
                            <div class="d-flex flex-wrap justify-content-between gap-2">
                                <div>
                                    <div class="fw-semibold">{{ $b->nombre }} {{ $b->apellido_paterno }} {{ $b->apellido_materno }}</div>
                                    <div class="text-muted small">{{ $b->curp }}</div>
                                </div>
                                <div class="d-flex flex-column align-items-end gap-1">
                                    <span class="badge text-bg-light border">Tarjeta: {{ $b->folio_tarjeta ?: 'Sin captura' }}</span>
                                    <span class="badge {{ $b->api_tj_sync_status === 'pending_sync' ? 'text-bg-primary' : ($b->api_tj_sync_status === 'synced' ? 'text-bg-success' : ($b->api_tj_sync_status === 'sync_failed' ? 'text-bg-danger' : 'text-bg-warning')) }}">
                                        API_TJ: {{ $b->api_tj_sync_status ?: 'sin_estado' }}
                                    </span>
                                </div>
                            </div>
                            <div class="row g-2 small text-muted">
                                <div class="col-sm-6"><i class="bi bi-geo-alt me-1"></i>{{ optional($b->municipio)->nombre ?? 'Sin municipio' }}</div>
                                <div class="col-sm-6"><i class="bi bi-diagram-3 me-1"></i>Seccional {{ optional($b->seccion)->seccional ?? 'N/D' }}</div>
                                <div class="col-sm-6"><i class="bi bi-telephone me-1"></i>{{ $b->telefono ?: 'Sin telefono' }}</div>
                                <div class="col-sm-6"><i class="bi bi-person-badge me-1"></i>{{ optional($b->creador)->name ?? 'Integracion API_TJ' }}</div>
                                <div class="col-sm-6"><i class="bi bi-envelope me-1"></i>{{ $b->email ?: 'Sin email' }}</div>
                                <div class="col-sm-6"><i class="bi bi-check2-square me-1"></i>{{ $b->api_tj_last_synced_at ? 'Ultimo sync '.optional($b->api_tj_last_synced_at)->format('Y-m-d H:i') : 'Sin sync exitoso' }}</div>
                            </div>
                            <div class="mt-auto d-flex flex-wrap gap-2">
                                <a class="btn btn-outline-primary btn-sm" href="{{ route('beneficiarios.edit', $b) }}">
                                    <i class="bi bi-pencil-square me-1"></i>Editar
                                </a>
                                @if(auth()->user()?->hasRole('admin'))
                                    <form action="{{ route('beneficiarios.destroy', $b) }}" method="POST" class="m-0" onsubmit="return confirm('Eliminar beneficiario?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-outline-danger btn-sm">
                                            <i class="bi bi-trash me-1"></i>Eliminar
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="col-12">
                        <div class="text-center text-muted py-4">No hay beneficiarios que coincidan con la busqueda actual.</div>
                    </div>
                @endforelse
            </div>
        </div>
        @if($beneficiarios->hasPages())
            <div class="card-footer">{{ $beneficiarios->links() }}</div>
        @endif
    </div>
</x-app-layout>
