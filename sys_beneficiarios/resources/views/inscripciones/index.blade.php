<x-app-layout>
    <x-slot name="header">
        <div class="d-flex align-items-center justify-content-between">
            <h2 class="h4 m-0">Inscripciones</h2>
            <div class="d-flex gap-2">
                <a href="{{ route('inscripciones.dashboard') }}" class="btn btn-outline-info">Dashboard</a>
                <a href="{{ route('inscripciones.index') }}" class="btn btn-primary">Nueva</a>
            </div>
        </div>
    </x-slot>

    @if(session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div class="card mb-3">
        <div class="card-body">
            <form class="row gy-2 gx-3 align-items-end" method="GET" action="{{ route('inscripciones.list') }}">
                <div class="col-12 col-md-4">
                    <label class="form-label">Busqueda</label>
                    <input type="text" name="q" value="{{ $q }}" class="form-control" placeholder="CURP, nombre o programa">
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label">Programa</label>
                    <select name="programa_id" class="form-select">
                        <option value="">-</option>
                        @foreach($programas as $id=>$nombre)
                            <option value="{{ $id }}" @selected(($filters['programa_id'] ?? '')==$id)>{{ $nombre }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label">Periodo</label>
                    <input type="month" name="periodo" value="{{ $filters['periodo'] ?? '' }}" class="form-control">
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label">Estatus</label>
                    <select name="estatus" class="form-select">
                        <option value="">-</option>
                        @foreach(['inscrito' => 'Inscrito', 'baja' => 'Baja', 'lista_espera' => 'Lista de espera'] as $key => $label)
                            <option value="{{ $key }}" @selected(($filters['estatus'] ?? '')===$key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-md-3 ms-auto text-end">
                    <a href="{{ route('inscripciones.list') }}" class="btn btn-outline-secondary me-2"><i class="bi bi-arrow-counterclockwise me-1"></i>Limpiar</a>
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
                        <th>Beneficiario</th>
                        <th>Programa</th>
                        <th>Periodo</th>
                        <th>Estatus</th>
                        <th>Capturista</th>
                        <th>Registrado</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($inscripciones as $inscripcion)
                        <tr>
                            <td>
                                <div class="fw-semibold">
                                    {{ $inscripcion->beneficiario->nombre }}
                                    {{ $inscripcion->beneficiario->apellido_paterno }}
                                    {{ $inscripcion->beneficiario->apellido_materno }}
                                </div>
                                <div class="text-white-50 small">{{ $inscripcion->beneficiario->curp }}</div>
                            </td>
                            <td>{{ $inscripcion->programa->nombre }}</td>
                            <td>{{ $inscripcion->periodo }}</td>
                            <td>
                                <span class="badge bg-secondary text-white">{{ ucfirst(str_replace('_', ' ', $inscripcion->estatus)) }}</span>
                                @if($inscripcion->fecha_renovacion)
                                    <span class="badge bg-info text-dark">Renovado</span>
                                @endif
                            </td>
                            <td>{{ optional($inscripcion->creador)->name ?? 'N/D' }}</td>
                            <td class="text-white-50">{{ optional($inscripcion->created_at)->format('Y-m-d') }}</td>
                            <td class="text-end">
                                <a class="btn btn-outline-secondary btn-sm" href="{{ route('inscripciones.edit', $inscripcion) }}"><i class="bi bi-pencil-square"></i></a>
                                <form action="{{ route('inscripciones.destroy', $inscripcion) }}" method="POST" class="d-inline" onsubmit="return confirm('Eliminar inscripcion?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">Sin registros</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($inscripciones->hasPages())
            <div class="card-footer">{{ $inscripciones->links() }}</div>
        @endif
    </div>
</x-app-layout>
