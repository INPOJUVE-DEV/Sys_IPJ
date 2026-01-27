<x-app-layout>
    <x-slot name="header">
        <div class="d-flex align-items-center justify-content-between">
            <h2 class="h4 m-0">Programas</h2>
            <a href="{{ route('programas.create') }}" class="btn btn-primary">Nuevo</a>
        </div>
    </x-slot>

    @if(session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div class="card mb-3">
        <div class="card-body">
            <form class="row gy-2 gx-3 align-items-end" method="GET">
                <div class="col-12 col-md-4">
                    <label class="form-label">Busqueda</label>
                    <input type="text" name="q" value="{{ $q }}" class="form-control" placeholder="Nombre o slug">
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label">Activo</label>
                    <select name="activo" class="form-select">
                        <option value="">-</option>
                        <option value="1" @selected(($activo ?? '') === '1')>Si</option>
                        <option value="0" @selected(($activo ?? '') === '0')>No</option>
                    </select>
                </div>
                <div class="col-12 col-md-3 ms-auto text-end">
                    <a href="{{ route('programas.index') }}" class="btn btn-outline-secondary me-2"><i class="bi bi-arrow-counterclockwise me-1"></i>Limpiar</a>
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
                        <th>Nombre</th>
                        <th>Slug</th>
                        <th>Periodo</th>
                        <th>Renovable</th>
                        <th>Activo</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($programas as $programa)
                        <tr>
                            <td class="fw-semibold">{{ $programa->nombre }}</td>
                            <td><span class="text-white-50">{{ $programa->slug }}</span></td>
                            <td>{{ ucfirst($programa->tipo_periodo) }}</td>
                            <td>{{ $programa->renovable ? 'Si' : 'No' }}</td>
                            <td>
                                <span class="badge {{ $programa->activo ? 'bg-success' : 'bg-secondary' }}">
                                    {{ $programa->activo ? 'Activo' : 'Inactivo' }}
                                </span>
                            </td>
                            <td class="text-end">
                                <a class="btn btn-outline-secondary btn-sm" href="{{ route('programas.edit', $programa) }}"><i class="bi bi-pencil-square"></i></a>
                                <form action="{{ route('programas.destroy', $programa) }}" method="POST" class="d-inline" onsubmit="return confirm('Eliminar programa?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">Sin registros</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($programas->hasPages())
            <div class="card-footer">{{ $programas->links() }}</div>
        @endif
    </div>
</x-app-layout>
