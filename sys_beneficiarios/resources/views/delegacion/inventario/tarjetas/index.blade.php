@php
    $statusLabels = [
        \App\Models\Tarjeta::STATUS_DISPONIBLE => 'Disponible',
        \App\Models\Tarjeta::STATUS_ASIGNADA_OFICINA => 'En region',
        \App\Models\Tarjeta::STATUS_ASIGNADA_USUARIO => 'Con capturista',
        \App\Models\Tarjeta::STATUS_CONSUMIDA => 'Capturada',
        \App\Models\Tarjeta::STATUS_DEVUELTA => 'Devuelta',
        \App\Models\Tarjeta::STATUS_BLOQUEADA => 'Bloqueada',
        \App\Models\Tarjeta::STATUS_EXTRAVIADA => 'Extraviada',
    ];
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
            <div>
                <h2 class="h4 m-0">Tarjetas de mi region</h2>
                <div class="text-muted small">Entrega tarjetas por cantidad, sin capturar folios.</div>
            </div>
            <a href="{{ route('stack.index') }}" class="btn btn-outline-primary">
                <i class="bi bi-bar-chart-line me-1"></i> Ver Stack
            </a>
        </div>
    </x-slot>

    @if(session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger">
            <strong>No se pudo guardar.</strong>
            <div class="small">Revisa la cantidad, municipio o capturista seleccionado.</div>
        </div>
    @endif

    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl"><div class="card border-0 shadow-sm"><div class="card-body"><div class="text-muted small">Total region</div><div class="h3 mb-0">{{ $summary['oficina'] }}</div></div></div></div>
        <div class="col-sm-6 col-xl"><div class="card border-0 shadow-sm"><div class="card-body"><div class="text-muted small">Listas para entregar</div><div class="h3 mb-0">{{ $summary['pendientes'] }}</div></div></div></div>
        <div class="col-sm-6 col-xl"><div class="card border-0 shadow-sm"><div class="card-body"><div class="text-muted small">Con capturistas</div><div class="h3 mb-0">{{ $summary['usuario'] }}</div></div></div></div>
        <div class="col-sm-6 col-xl"><div class="card border-0 shadow-sm"><div class="card-body"><div class="text-muted small">Capturadas</div><div class="h3 mb-0">{{ $summary['consumida'] }}</div></div></div></div>
        <div class="col-sm-6 col-xl"><div class="card border-0 shadow-sm"><div class="card-body"><div class="text-muted small">Incidencias</div><div class="h3 mb-0">{{ $summary['incidencias'] }}</div></div></div></div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-12 col-lg-5" id="entregar-tarjetas">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white">
                    <div class="fw-semibold">Entregar tarjetas a capturista</div>
                    <div class="small text-muted">Elige cantidad y municipio. No necesitas escribir folios.</div>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('delegacion.inventario.tarjetas.assignRange') }}" class="row g-3">
                        @csrf
                        <div class="col-md-5">
                            <label class="form-label">Cantidad</label>
                            <input name="cantidad" type="number" min="1" class="form-control form-control-lg" value="{{ old('cantidad') }}" required>
                        </div>
                        <div class="col-md-7">
                            <label class="form-label">Capturista</label>
                            <select name="usuario_uuid" class="form-select form-select-lg" required>
                                @foreach($capturistas as $capturista)
                                    <option value="{{ $capturista->uuid }}">{{ $capturista->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Municipio a trabajar</label>
                            <select name="municipio_id" class="form-select">
                                <option value="">Sin municipio especifico</option>
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
                            <button class="btn btn-primary btn-lg" type="submit">Entregar tarjetas</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-12 col-lg-7">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white">
                    <div class="fw-semibold">Buscar en mi region</div>
                    <div class="small text-muted">Filtra el resumen para revisar entregas.</div>
                </div>
                <div class="card-body">
                    <form method="GET" class="row g-2">
                        <div class="col-md-4">
                            <select name="municipio_id" class="form-select">
                                <option value="">Todos los municipios</option>
                                @foreach($municipios as $municipio)
                                    <option value="{{ $municipio->id }}" @selected(($filters['municipio_id'] ?? '') == $municipio->id)>{{ $municipio->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <select name="estatus" class="form-select">
                                <option value="">Todos los estados</option>
                                @foreach($statusLabels as $status => $label)
                                    <option value="{{ $status }}" @selected(($filters['estatus'] ?? '') === $status)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <select name="usuario_uuid" class="form-select">
                                <option value="">Todos los responsables</option>
                                @foreach($capturistas as $capturista)
                                    <option value="{{ $capturista->uuid }}" @selected(($filters['usuario_uuid'] ?? '') === $capturista->uuid)>{{ $capturista->name }}</option>
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
        <div class="card-header bg-white">
            <div class="fw-semibold">Resumen de tarjetas</div>
            <div class="small text-muted">Agrupado por municipio, responsable y estado.</div>
        </div>
        <div class="card-body table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>Municipio</th>
                        <th>Responsable</th>
                        <th>Estado</th>
                        <th class="text-end">Cantidad</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($groups as $group)
                        @php
                            $municipio = $municipiosById->get($group->municipio_id);
                            $responsable = $group->usuario_uuid ? $capturistasByUuid->get($group->usuario_uuid) : null;
                        @endphp
                        <tr>
                            <td>{{ $municipio?->nombre ?? 'Sin municipio especifico' }}</td>
                            <td>{{ $responsable?->name ?? 'Stock de la region' }}</td>
                            <td><span class="badge text-bg-light">{{ $statusLabels[$group->estatus] ?? $group->estatus }}</span></td>
                            <td class="text-end h5 mb-0">{{ $group->total }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-muted">Aun no hay tarjetas en esta region.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($groups->hasPages())
            <div class="card-footer bg-white">{{ $groups->links() }}</div>
        @endif
    </div>
</x-app-layout>
