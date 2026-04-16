@php
    $statusLabels = [
        \App\Models\Tarjeta::STATUS_DISPONIBLE => 'En bodega central',
        \App\Models\Tarjeta::STATUS_ASIGNADA_OFICINA => 'En region',
        \App\Models\Tarjeta::STATUS_ASIGNADA_USUARIO => 'Entregadas a capturista',
        \App\Models\Tarjeta::STATUS_CONSUMIDA => 'Capturadas',
        \App\Models\Tarjeta::STATUS_DEVUELTA => 'Devueltas',
        \App\Models\Tarjeta::STATUS_BLOQUEADA => 'Bloqueadas',
        \App\Models\Tarjeta::STATUS_EXTRAVIADA => 'Extraviadas',
    ];
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
            <div>
                <h2 class="h4 m-0">Inventario de tarjetas</h2>
                <div class="text-muted small">Captura cantidades. El sistema controla las tarjetas internamente.</div>
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
            <div class="small">Revisa la cantidad, region, municipio o capturista seleccionado.</div>
        </div>
    @endif

    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-2"><div class="card border-0 shadow-sm"><div class="card-body"><div class="text-muted small">Total</div><div class="h3 mb-0">{{ $summary['total'] }}</div></div></div></div>
        <div class="col-sm-6 col-xl-2"><div class="card border-0 shadow-sm"><div class="card-body"><div class="text-muted small">Central</div><div class="h3 mb-0">{{ $summary['disponible'] }}</div></div></div></div>
        <div class="col-sm-6 col-xl-2"><div class="card border-0 shadow-sm"><div class="card-body"><div class="text-muted small">En regiones</div><div class="h3 mb-0">{{ $summary['oficina'] }}</div></div></div></div>
        <div class="col-sm-6 col-xl-2"><div class="card border-0 shadow-sm"><div class="card-body"><div class="text-muted small">Con capturistas</div><div class="h3 mb-0">{{ $summary['usuario'] }}</div></div></div></div>
        <div class="col-sm-6 col-xl-2"><div class="card border-0 shadow-sm"><div class="card-body"><div class="text-muted small">Capturadas</div><div class="h3 mb-0">{{ $summary['consumida'] }}</div></div></div></div>
        <div class="col-sm-6 col-xl-2"><div class="card border-0 shadow-sm"><div class="card-body"><div class="text-muted small">Incidencias</div><div class="h3 mb-0">{{ $summary['incidencias'] }}</div></div></div></div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-12 col-xl-4" id="agregar-tarjetas">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white">
                    <div class="fw-semibold">Agregar tarjetas</div>
                    <div class="small text-muted">Cuando llegan tarjetas nuevas al stock.</div>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.inventario.tarjetas.storeRange') }}" class="row g-3">
                        @csrf
                        <div class="col-md-5">
                            <label class="form-label">Cantidad</label>
                            <input name="cantidad" type="number" min="1" class="form-control form-control-lg" value="{{ old('cantidad') }}" required>
                        </div>
                        <div class="col-md-7">
                            <label class="form-label">Guardar en</label>
                            <select name="oficina_id" class="form-select form-select-lg" required>
                                @foreach($offices as $office)
                                    <option value="{{ $office->id }}" @selected((string) old('oficina_id') === (string) $office->id)>
                                        {{ $office->nombre }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Municipio destino (opcional)</label>
                            <select name="municipio_id" class="form-select">
                                <option value="">Sin municipio especifico</option>
                                @foreach($municipios as $municipio)
                                    <option value="{{ $municipio->id }}" @selected((string) old('municipio_id') === (string) $municipio->id)>
                                        {{ $municipio->region ?: 'Sin region' }} - {{ $municipio->nombre }}
                                    </option>
                                @endforeach
                            </select>
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

        <div class="col-12 col-xl-4" id="mover-tarjetas">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white">
                    <div class="fw-semibold">Mover a region o municipio</div>
                    <div class="small text-muted">Para repartir desde central hacia una delegacion.</div>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.inventario.tarjetas.transferRange') }}" class="row g-3">
                        @csrf
                        <div class="col-md-5">
                            <label class="form-label">Cantidad</label>
                            <input name="cantidad" type="number" min="1" class="form-control form-control-lg" required>
                        </div>
                        <div class="col-md-7">
                            <label class="form-label">Desde</label>
                            <select name="origen_oficina_id" class="form-select form-select-lg">
                                <option value="">Central por default</option>
                                @foreach($offices as $office)
                                    <option value="{{ $office->id }}">{{ $office->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Enviar a region</label>
                            <select name="destino_oficina_id" class="form-select form-select-lg" required>
                                @foreach($offices as $office)
                                    <option value="{{ $office->id }}">{{ $office->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Municipio destino</label>
                            <select name="municipio_id" class="form-select">
                                <option value="">Solo region, sin municipio</option>
                                @foreach($municipios as $municipio)
                                    <option value="{{ $municipio->id }}">{{ $municipio->region ?: 'Sin region' }} - {{ $municipio->nombre }}</option>
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

        <div class="col-12 col-xl-4" id="entregar-tarjetas">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white">
                    <div class="fw-semibold">Entregar a capturista</div>
                    <div class="small text-muted">Para que una persona pueda capturar beneficiarios.</div>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.inventario.tarjetas.assignRange') }}" class="row g-3">
                        @csrf
                        <div class="col-md-5">
                            <label class="form-label">Cantidad</label>
                            <input name="cantidad" type="number" min="1" class="form-control form-control-lg" required>
                        </div>
                        <div class="col-md-7">
                            <label class="form-label">Capturista</label>
                            <select name="usuario_uuid" class="form-select form-select-lg" required>
                                @foreach($users as $user)
                                    <option value="{{ $user->uuid }}">{{ $user->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Municipio a trabajar</label>
                            <select name="municipio_id" class="form-select">
                                <option value="">Sin municipio especifico</option>
                                @foreach($municipios as $municipio)
                                    <option value="{{ $municipio->id }}">{{ $municipio->region ?: 'Sin region' }} - {{ $municipio->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Nota interna</label>
                            <input name="observaciones" class="form-control" placeholder="Ej. entrega a Juan">
                        </div>
                        <div class="col-12 d-grid">
                            <button class="btn btn-outline-primary btn-lg" type="submit">Entregar tarjetas</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-white">
            <div class="fw-semibold">Resumen del stock</div>
            <div class="small text-muted">Agrupado por region, municipio, responsable y estado.</div>
        </div>
        <div class="card-body">
            <form method="GET" class="row g-2 mb-3">
                <div class="col-md-3">
                    <select name="oficina_id" class="form-select">
                        <option value="">Todas las regiones</option>
                        @foreach($offices as $office)
                            <option value="{{ $office->id }}" @selected(($filters['oficina_id'] ?? '') == $office->id)>{{ $office->nombre }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="municipio_id" class="form-select">
                        <option value="">Todos los municipios</option>
                        @foreach($municipios as $municipio)
                            <option value="{{ $municipio->id }}" @selected(($filters['municipio_id'] ?? '') == $municipio->id)>{{ $municipio->nombre }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="estatus" class="form-select">
                        <option value="">Todos los estados</option>
                        @foreach($statusLabels as $status => $label)
                            <option value="{{ $status }}" @selected(($filters['estatus'] ?? '') === $status)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="usuario_uuid" class="form-select">
                        <option value="">Todos los responsables</option>
                        @foreach($users as $user)
                            <option value="{{ $user->uuid }}" @selected(($filters['usuario_uuid'] ?? '') === $user->uuid)>{{ $user->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <button class="btn btn-outline-secondary w-100" type="submit">Ver resultado</button>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>Region</th>
                            <th>Municipio</th>
                            <th>Responsable</th>
                            <th>Estado</th>
                            <th class="text-end">Cantidad</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($groups as $group)
                            @php
                                $office = $officesById->get($group->oficina_id);
                                $municipio = $municipiosById->get($group->municipio_id);
                                $responsable = $group->usuario_uuid ? $usersByUuid->get($group->usuario_uuid) : null;
                            @endphp
                            <tr>
                                <td>{{ $office?->nombre ?? 'Sin region' }}</td>
                                <td>{{ $municipio?->nombre ?? 'Sin municipio especifico' }}</td>
                                <td>{{ $responsable?->name ?? ($office?->nombre ?? 'Stock general') }}</td>
                                <td><span class="badge text-bg-light">{{ $statusLabels[$group->estatus] ?? $group->estatus }}</span></td>
                                <td class="text-end h5 mb-0">{{ $group->total }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-muted">Aun no hay tarjetas registradas.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($groups->hasPages())
            <div class="card-footer bg-white">{{ $groups->links() }}</div>
        @endif
    </div>
</x-app-layout>
