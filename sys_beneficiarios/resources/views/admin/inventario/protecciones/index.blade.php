<x-app-layout>
    <x-slot name="header">
        <h2 class="h4 m-0">Inventario de Protecciones</h2>
    </x-slot>

    @if(session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger">No se pudo completar la operacion solicitada sobre protecciones.</div>
    @endif

    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-3"><div class="card"><div class="card-body"><div class="text-muted">Total</div><div class="h3 mb-0">{{ $summary['total'] }}</div></div></div></div>
        <div class="col-sm-6 col-xl-3"><div class="card"><div class="card-body"><div class="text-muted">Disponibles</div><div class="h3 mb-0">{{ $summary['disponible'] }}</div></div></div></div>
        <div class="col-sm-6 col-xl-3"><div class="card"><div class="card-body"><div class="text-muted">Prestadas</div><div class="h3 mb-0">{{ $summary['prestada'] }}</div></div></div></div>
        <div class="col-sm-6 col-xl-3"><div class="card"><div class="card-body"><div class="text-muted">Inactivas</div><div class="h3 mb-0">{{ $summary['inactiva'] }}</div></div></div></div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-12 col-xl-5">
            <div class="card shadow-sm h-100">
                <div class="card-header">Alta por lote</div>
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.inventario.protecciones.storeBatch') }}" class="row g-3">
                        @csrf
                        <div class="col-md-6">
                            <label class="form-label">Tipo</label>
                            <input name="tipo" class="form-control" value="{{ old('tipo') }}" placeholder="Ej. Casco" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Usuario Skate Plaza</label>
                            <select name="usuario_uuid" class="form-select" required>
                                <option value="">Selecciona</option>
                                @foreach($users as $user)
                                    <option value="{{ $user->uuid }}" @selected(old('usuario_uuid') === $user->uuid)>{{ $user->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Numeros de inventario</label>
                            <textarea name="numeros_inventario" rows="7" class="form-control" placeholder="Uno por linea o separados por coma" required>{{ old('numeros_inventario') }}</textarea>
                            <div class="form-text">Cada numero se registra como una proteccion individual.</div>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Observaciones</label>
                            <input name="observaciones" class="form-control" value="{{ old('observaciones') }}">
                        </div>
                        <div class="col-12 text-end">
                            <button class="btn btn-primary" type="submit">Crear protecciones</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-12 col-xl-7">
            <div class="card shadow-sm h-100">
                <div class="card-header">Consulta de inventario</div>
                <div class="card-body">
                    <form method="GET" class="row g-2">
                        <div class="col-md-3">
                            <input name="q" class="form-control" placeholder="Numero inventario" value="{{ $filters['q'] ?? '' }}">
                        </div>
                        <div class="col-md-3">
                            <select name="tipo" class="form-select">
                                <option value="">Todos los tipos</option>
                                @foreach($types as $type)
                                    <option value="{{ $type }}" @selected(($filters['tipo'] ?? '') === $type)>{{ $type }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <select name="estatus" class="form-select">
                                <option value="">Todos los estatus</option>
                                @foreach([\App\Models\Proteccion::STATUS_DISPONIBLE, \App\Models\Proteccion::STATUS_PRESTADA, \App\Models\Proteccion::STATUS_INACTIVA] as $status)
                                    <option value="{{ $status }}" @selected(($filters['estatus'] ?? '') === $status)>{{ $status }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <select name="usuario_uuid" class="form-select">
                                <option value="">Todos los usuarios</option>
                                @foreach($users as $user)
                                    <option value="{{ $user->uuid }}" @selected(($filters['usuario_uuid'] ?? '') === $user->uuid)>{{ $user->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12 text-end">
                            <button class="btn btn-outline-secondary" type="submit">Filtrar</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header">Protecciones registradas</div>
        <div class="card-body table-responsive">
            <table class="table table-sm align-middle">
                <thead>
                    <tr>
                        <th>Numero</th>
                        <th>Tipo</th>
                        <th>Estatus</th>
                        <th>Responsable</th>
                        <th>Beneficiario</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($protecciones as $proteccion)
                        <tr>
                            <td class="font-monospace">{{ $proteccion->numero_inventario }}</td>
                            <td>{{ $proteccion->tipo }}</td>
                            <td>{{ $proteccion->estatus }}</td>
                            <td>{{ $proteccion->usuario?->name ?? 'Sin usuario' }}</td>
                            <td>
                                @if($proteccion->beneficiario)
                                    {{ $proteccion->beneficiario->nombre }} {{ $proteccion->beneficiario->apellido_paterno }}
                                @else
                                    Sin prestamo
                                @endif
                            </td>
                            <td class="text-end">
                                <div class="d-flex flex-column gap-2 align-items-end">
                                    <form method="POST" action="{{ route('admin.inventario.protecciones.transfer', $proteccion) }}" class="d-inline-flex gap-2">
                                        @csrf
                                        <select name="usuario_uuid" class="form-select form-select-sm" @disabled($proteccion->estatus === \App\Models\Proteccion::STATUS_PRESTADA)>
                                            @foreach($users as $user)
                                                <option value="{{ $user->uuid }}" @selected($proteccion->usuario_uuid === $user->uuid)>{{ $user->name }}</option>
                                            @endforeach
                                        </select>
                                        <button class="btn btn-sm btn-outline-primary" type="submit" @disabled($proteccion->estatus === \App\Models\Proteccion::STATUS_PRESTADA)>Transferir</button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.inventario.protecciones.status', $proteccion) }}" class="d-inline-flex gap-2">
                                        @csrf
                                        <select name="estatus" class="form-select form-select-sm">
                                            <option value="{{ \App\Models\Proteccion::STATUS_DISPONIBLE }}" @selected($proteccion->estatus === \App\Models\Proteccion::STATUS_DISPONIBLE)>disponible</option>
                                            <option value="{{ \App\Models\Proteccion::STATUS_INACTIVA }}" @selected($proteccion->estatus === \App\Models\Proteccion::STATUS_INACTIVA)>inactiva</option>
                                        </select>
                                        <button class="btn btn-sm btn-outline-danger" type="submit">Actualizar</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-muted">Sin protecciones registradas</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($protecciones->hasPages())
            <div class="card-footer">{{ $protecciones->links() }}</div>
        @endif
    </div>
</x-app-layout>
