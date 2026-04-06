<x-app-layout>
    <x-slot name="header">
        <h2 class="h4 m-0">Inventario de Vales</h2>
    </x-slot>

    @if(session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger">Revisa los datos capturados del inventario de vales.</div>
    @endif

    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl"><div class="card"><div class="card-body"><div class="text-muted">Total blocs</div><div class="h3 mb-0">{{ $summary['total'] }}</div></div></div></div>
        <div class="col-sm-6 col-xl"><div class="card"><div class="card-body"><div class="text-muted">En oficina</div><div class="h3 mb-0">{{ $summary['oficina'] }}</div></div></div></div>
        <div class="col-sm-6 col-xl"><div class="card"><div class="card-body"><div class="text-muted">En usuario</div><div class="h3 mb-0">{{ $summary['usuario'] }}</div></div></div></div>
        <div class="col-sm-6 col-xl"><div class="card"><div class="card-body"><div class="text-muted">Cerrados</div><div class="h3 mb-0">{{ $summary['cerrado'] }}</div></div></div></div>
        <div class="col-sm-6 col-xl"><div class="card"><div class="card-body"><div class="text-muted">Incidencias</div><div class="h3 mb-0">{{ $summary['incidencias'] }}</div></div></div></div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-12 col-lg-4">
            <div class="card shadow-sm h-100">
                <div class="card-header">Alta de bloc</div>
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.inventario.vales.store') }}" class="row g-2">
                        @csrf
                        <div class="col-md-6">
                            <label class="form-label">Folio inicio</label>
                            <input name="folio_inicio" type="number" min="0" class="form-control" value="{{ old('folio_inicio') }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Folio fin</label>
                            <input name="folio_fin" type="number" min="0" class="form-control" value="{{ old('folio_fin') }}" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Oficina destino</label>
                            <select name="oficina_id" class="form-select" required>
                                @foreach($offices as $office)
                                    <option value="{{ $office->id }}" @selected(old('oficina_id') == $office->id)>{{ $office->nombre }} ({{ $office->tipo }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Observaciones</label>
                            <input name="observaciones" class="form-control" value="{{ old('observaciones') }}">
                        </div>
                        <div class="col-12 text-end">
                            <button class="btn btn-primary" type="submit">Crear bloc</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-12 col-lg-8">
            <div class="card shadow-sm h-100">
                <div class="card-header">Consulta rapida</div>
                <div class="card-body">
                    <form method="GET" class="row g-2">
                        <div class="col-md-4">
                            <select name="oficina_id" class="form-select">
                                <option value="">Todas las oficinas</option>
                                @foreach($offices as $office)
                                    <option value="{{ $office->id }}" @selected(($filters['oficina_id'] ?? '') == $office->id)>{{ $office->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <select name="estatus" class="form-select">
                                <option value="">Todos los estatus</option>
                                @foreach([\App\Models\ValeBloc::STATUS_DISPONIBLE, \App\Models\ValeBloc::STATUS_ASIGNADO_OFICINA, \App\Models\ValeBloc::STATUS_ASIGNADO_USUARIO, \App\Models\ValeBloc::STATUS_CERRADO, \App\Models\ValeBloc::STATUS_DEVUELTO, \App\Models\ValeBloc::STATUS_BLOQUEADO, \App\Models\ValeBloc::STATUS_EXTRAVIADO] as $status)
                                    <option value="{{ $status }}" @selected(($filters['estatus'] ?? '') === $status)>{{ $status }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <select name="usuario_uuid" class="form-select">
                                <option value="">Todos los responsables</option>
                                @foreach($users as $user)
                                    <option value="{{ $user->uuid }}" @selected(($filters['usuario_uuid'] ?? '') === $user->uuid)>{{ $user->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12 text-end">
                            <button class="btn btn-outline-secondary" type="submit">Filtrar</button>
                        </div>
                    </form>
                    <div class="small text-muted mt-3">Cada bloc debe cubrir exactamente 1000 folios consecutivos y no puede traslaparse con otro.</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header">Blocs registrados</div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm align-middle">
                    <thead>
                        <tr>
                            <th>Rango</th>
                            <th>Estatus</th>
                            <th>Oficina</th>
                            <th>Responsable</th>
                            <th>Observaciones</th>
                            <th class="text-end">Operaciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($valeBlocs as $bloc)
                            <tr>
                                <td class="font-monospace">{{ $bloc->folio_inicio }} - {{ $bloc->folio_fin }}</td>
                                <td>{{ $bloc->estatus }}</td>
                                <td>{{ $bloc->oficina?->nombre ?? 'N/D' }}</td>
                                <td>{{ $bloc->usuario?->name ?? 'Sin asignar' }}</td>
                                <td>{{ $bloc->observaciones ?? 'Sin notas' }}</td>
                                <td class="text-end">
                                    <div class="d-flex flex-column gap-2 align-items-end">
                                        <form method="POST" action="{{ route('admin.inventario.vales.transfer', $bloc) }}" class="d-inline-flex gap-2">
                                            @csrf
                                            <select name="destino_oficina_id" class="form-select form-select-sm" required>
                                                @foreach($offices as $office)
                                                    <option value="{{ $office->id }}">{{ $office->nombre }}</option>
                                                @endforeach
                                            </select>
                                            <button class="btn btn-sm btn-outline-primary" type="submit">Transferir</button>
                                        </form>
                                        <form method="POST" action="{{ route('admin.inventario.vales.assign', $bloc) }}" class="d-inline-flex gap-2">
                                            @csrf
                                            <select name="usuario_uuid" class="form-select form-select-sm" required>
                                                @foreach($users as $user)
                                                    <option value="{{ $user->uuid }}">{{ $user->name }}</option>
                                                @endforeach
                                            </select>
                                            <button class="btn btn-sm btn-outline-secondary" type="submit">Asignar</button>
                                        </form>
                                        <form method="POST" action="{{ route('admin.inventario.vales.status', $bloc) }}" class="d-inline-flex gap-2">
                                            @csrf
                                            <select name="estatus" class="form-select form-select-sm">
                                                <option value="{{ \App\Models\ValeBloc::STATUS_DEVUELTO }}">devuelto</option>
                                                <option value="{{ \App\Models\ValeBloc::STATUS_CERRADO }}">cerrado</option>
                                                <option value="{{ \App\Models\ValeBloc::STATUS_BLOQUEADO }}">bloqueado</option>
                                                <option value="{{ \App\Models\ValeBloc::STATUS_EXTRAVIADO }}">extraviado</option>
                                            </select>
                                            <button class="btn btn-sm btn-outline-danger" type="submit">Actualizar</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-muted">Sin blocs registrados</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($valeBlocs->hasPages())
            <div class="card-footer">{{ $valeBlocs->links() }}</div>
        @endif
    </div>
</x-app-layout>
