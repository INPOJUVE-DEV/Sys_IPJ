<x-app-layout>
    <x-slot name="header">
        <h2 class="h4 m-0">Inventario de Tarjetas</h2>
    </x-slot>

    @if(session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger">No se pudo completar la operacion solicitada sobre tarjetas.</div>
    @endif

    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-2"><div class="card"><div class="card-body"><div class="text-muted">Total</div><div class="h3 mb-0">{{ $summary['total'] }}</div></div></div></div>
        <div class="col-sm-6 col-xl-2"><div class="card"><div class="card-body"><div class="text-muted">Disponibles</div><div class="h3 mb-0">{{ $summary['disponible'] }}</div></div></div></div>
        <div class="col-sm-6 col-xl-2"><div class="card"><div class="card-body"><div class="text-muted">En oficina</div><div class="h3 mb-0">{{ $summary['oficina'] }}</div></div></div></div>
        <div class="col-sm-6 col-xl-2"><div class="card"><div class="card-body"><div class="text-muted">En usuario</div><div class="h3 mb-0">{{ $summary['usuario'] }}</div></div></div></div>
        <div class="col-sm-6 col-xl-2"><div class="card"><div class="card-body"><div class="text-muted">Consumidas</div><div class="h3 mb-0">{{ $summary['consumida'] }}</div></div></div></div>
        <div class="col-sm-6 col-xl-2"><div class="card"><div class="card-body"><div class="text-muted">Incidencias</div><div class="h3 mb-0">{{ $summary['incidencias'] }}</div></div></div></div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-12 col-xl-4">
            <div class="card shadow-sm h-100">
                <div class="card-header">Alta masiva por rango</div>
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.inventario.tarjetas.storeRange') }}" class="row g-2">
                        @csrf
                        <div class="col-md-4">
                            <label class="form-label">Prefijo</label>
                            <input name="prefijo" class="form-control" value="{{ old('prefijo') }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Desde</label>
                            <input name="folio_desde" type="number" min="0" class="form-control" value="{{ old('folio_desde') }}" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Hasta</label>
                            <input name="folio_hasta" type="number" min="0" class="form-control" value="{{ old('folio_hasta') }}" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Padding</label>
                            <input name="padding" type="number" min="0" max="12" class="form-control" value="{{ old('padding', 0) }}">
                        </div>
                        <div class="col-md-8">
                            <label class="form-label">Oficina destino</label>
                            <select name="oficina_id" class="form-select" required>
                                @foreach($offices as $office)
                                    <option value="{{ $office->id }}">{{ $office->nombre }} ({{ $office->tipo }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Observaciones</label>
                            <input name="observaciones" class="form-control" value="{{ old('observaciones') }}">
                        </div>
                        <div class="col-12 text-end">
                            <button class="btn btn-primary" type="submit">Crear rango</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-12 col-xl-4">
            <div class="card shadow-sm h-100">
                <div class="card-header">Transferir rango a oficina</div>
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.inventario.tarjetas.transferRange') }}" class="row g-2">
                        @csrf
                        <div class="col-md-4"><label class="form-label">Prefijo</label><input name="prefijo" class="form-control"></div>
                        <div class="col-md-4"><label class="form-label">Desde</label><input name="folio_desde" type="number" min="0" class="form-control" required></div>
                        <div class="col-md-4"><label class="form-label">Hasta</label><input name="folio_hasta" type="number" min="0" class="form-control" required></div>
                        <div class="col-md-4"><label class="form-label">Padding</label><input name="padding" type="number" min="0" max="12" class="form-control" value="0"></div>
                        <div class="col-md-8">
                            <label class="form-label">Oficina destino</label>
                            <select name="destino_oficina_id" class="form-select" required>
                                @foreach($offices as $office)
                                    <option value="{{ $office->id }}">{{ $office->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12"><label class="form-label">Observaciones</label><input name="observaciones" class="form-control"></div>
                        <div class="col-12 text-end"><button class="btn btn-outline-primary" type="submit">Transferir</button></div>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-12 col-xl-4">
            <div class="card shadow-sm h-100">
                <div class="card-header">Asignar rango a usuario</div>
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.inventario.tarjetas.assignRange') }}" class="row g-2">
                        @csrf
                        <div class="col-md-4"><label class="form-label">Prefijo</label><input name="prefijo" class="form-control"></div>
                        <div class="col-md-4"><label class="form-label">Desde</label><input name="folio_desde" type="number" min="0" class="form-control" required></div>
                        <div class="col-md-4"><label class="form-label">Hasta</label><input name="folio_hasta" type="number" min="0" class="form-control" required></div>
                        <div class="col-md-4"><label class="form-label">Padding</label><input name="padding" type="number" min="0" max="12" class="form-control" value="0"></div>
                        <div class="col-md-8">
                            <label class="form-label">Usuario destino</label>
                            <select name="usuario_uuid" class="form-select" required>
                                @foreach($users as $user)
                                    <option value="{{ $user->uuid }}">{{ $user->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12"><label class="form-label">Observaciones</label><input name="observaciones" class="form-control"></div>
                        <div class="col-12 text-end"><button class="btn btn-outline-primary" type="submit">Asignar</button></div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header">Tarjetas registradas</div>
        <div class="card-body">
            <form method="GET" class="row g-2 mb-3">
                <div class="col-md-3"><input name="q" class="form-control" placeholder="Buscar folio" value="{{ $filters['q'] ?? '' }}"></div>
                <div class="col-md-3">
                    <select name="oficina_id" class="form-select">
                        <option value="">Todas las oficinas</option>
                        @foreach($offices as $office)
                            <option value="{{ $office->id }}" @selected(($filters['oficina_id'] ?? '') == $office->id)>{{ $office->nombre }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="estatus" class="form-select">
                        <option value="">Todos los estatus</option>
                        @foreach([\App\Models\Tarjeta::STATUS_DISPONIBLE, \App\Models\Tarjeta::STATUS_ASIGNADA_OFICINA, \App\Models\Tarjeta::STATUS_ASIGNADA_USUARIO, \App\Models\Tarjeta::STATUS_CONSUMIDA, \App\Models\Tarjeta::STATUS_DEVUELTA, \App\Models\Tarjeta::STATUS_BLOQUEADA, \App\Models\Tarjeta::STATUS_EXTRAVIADA] as $status)
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
                <div class="col-md-3 text-end"><button class="btn btn-outline-secondary w-100" type="submit">Filtrar</button></div>
            </form>

            <div class="table-responsive">
                <table class="table table-sm align-middle">
                    <thead>
                        <tr>
                            <th>Folio</th>
                            <th>Estatus</th>
                            <th>Oficina</th>
                            <th>Usuario</th>
                            <th>Beneficiario</th>
                            <th class="text-end">Acción rápida</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($tarjetas as $tarjeta)
                            <tr>
                                <td class="font-monospace">{{ $tarjeta->folio }}</td>
                                <td>{{ $tarjeta->estatus }}</td>
                                <td>{{ $tarjeta->oficina?->nombre ?? 'N/D' }}</td>
                                <td>{{ $tarjeta->usuario?->name ?? 'Sin asignar' }}</td>
                                <td>
                                    @if($tarjeta->beneficiario)
                                        {{ $tarjeta->beneficiario->nombre }} {{ $tarjeta->beneficiario->apellido_paterno }}
                                    @else
                                        Sin consumo
                                    @endif
                                </td>
                                <td class="text-end">
                                    <form method="POST" action="{{ route('admin.inventario.tarjetas.status', $tarjeta) }}" class="d-inline-flex gap-2">
                                        @csrf
                                        <select name="estatus" class="form-select form-select-sm">
                                            <option value="{{ \App\Models\Tarjeta::STATUS_DEVUELTA }}">devuelta</option>
                                            <option value="{{ \App\Models\Tarjeta::STATUS_BLOQUEADA }}">bloqueada</option>
                                            <option value="{{ \App\Models\Tarjeta::STATUS_EXTRAVIADA }}">extraviada</option>
                                        </select>
                                        <button class="btn btn-sm btn-outline-danger" type="submit">Actualizar</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-muted">Sin tarjetas registradas</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($tarjetas->hasPages())
            <div class="card-footer">{{ $tarjetas->links() }}</div>
        @endif
    </div>
</x-app-layout>
