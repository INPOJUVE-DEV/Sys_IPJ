<x-app-layout>
    <x-slot name="header">
        <div class="d-flex align-items-center justify-content-between">
            <h2 class="h4 m-0">Eventos</h2>
            <a href="{{ route('eventos.create') }}" class="btn btn-primary">Nuevo evento</a>
        </div>
    </x-slot>

    @if(session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <div class="card mb-3">
        <div class="card-body">
            <form class="row gy-2 gx-3 align-items-end" method="GET">
                <div class="col-12 col-md-3">
                    <label class="form-label">Busqueda</label>
                    <input type="text" name="q" value="{{ $q }}" class="form-control" placeholder="Descripcion o lugar">
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label">Tipo</label>
                    <select name="evento_tipo_id" class="form-select">
                        <option value="">Todos</option>
                        @foreach($tipos as $tipo)
                            <option value="{{ $tipo->id }}" @selected((string) ($filters['evento_tipo_id'] ?? '') === (string) $tipo->id)>
                                {{ $tipo->nombre }}{{ ! $tipo->activo ? ' (inactivo)' : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label">Municipio</label>
                    <select name="municipio_id" class="form-select">
                        <option value="">Todos</option>
                        @foreach($municipios as $municipio)
                            <option value="{{ $municipio->id }}" @selected((string) ($filters['municipio_id'] ?? '') === (string) $municipio->id)>{{ $municipio->nombre }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label">Participacion</label>
                    <select name="rol_participacion" class="form-select">
                        <option value="">Todas</option>
                        @foreach($rolesParticipacion as $value => $label)
                            <option value="{{ $value }}" @selected(($filters['rol_participacion'] ?? '') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 text-end">
                    <a href="{{ route('eventos.index') }}" class="btn btn-outline-secondary me-2"><i class="bi bi-arrow-counterclockwise me-1"></i>Limpiar</a>
                    <button class="btn btn-primary" type="submit"><i class="bi bi-funnel me-1"></i>Filtrar</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table table-dark table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Tipo</th>
                        <th>Evento</th>
                        <th>Municipio</th>
                        <th>Participacion</th>
                        <th class="text-end">Asistentes</th>
                        <th>Evidencias</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($eventos as $evento)
                        <tr>
                            <td>{{ $evento->tipo?->nombre }}</td>
                            <td>
                                <div class="fw-semibold">{{ $evento->lugar }}</div>
                                <div class="small text-white-50">{{ \Illuminate\Support\Str::limit($evento->descripcion, 90) }}</div>
                                @if(auth()->user()?->hasRole('admin'))
                                    <div class="small text-white-50">Capturo: {{ $evento->creador?->name ?? 'Sin usuario' }}</div>
                                @endif
                            </td>
                            <td>{{ $evento->municipio?->nombre ?? 'Sin municipio' }}</td>
                            <td>{{ $rolesParticipacion[$evento->rol_participacion] ?? $evento->rol_participacion }}</td>
                            <td class="text-end">{{ number_format($evento->total_asistentes) }}</td>
                            <td>
                                @if($evento->evidencia_url)
                                    <a href="{{ $evento->evidencia_url }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary">
                                        <i class="bi bi-link-45deg me-1"></i>Abrir
                                    </a>
                                @else
                                    <span class="text-white-50">Sin link</span>
                                @endif
                            </td>
                            <td class="text-end">
                                @can('update', $evento)
                                    <a class="btn btn-outline-secondary btn-sm" href="{{ route('eventos.edit', $evento) }}"><i class="bi bi-pencil-square"></i></a>
                                @endcan
                                @can('delete', $evento)
                                    <form action="{{ route('eventos.destroy', $evento) }}" method="POST" class="d-inline" onsubmit="return confirm('Eliminar evento?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i></button>
                                    </form>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">Sin eventos registrados</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($eventos->hasPages())
            <div class="card-footer">{{ $eventos->links() }}</div>
        @endif
    </div>
</x-app-layout>
