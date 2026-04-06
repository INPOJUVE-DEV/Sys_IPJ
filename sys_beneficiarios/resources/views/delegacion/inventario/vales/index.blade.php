<x-app-layout>
    <x-slot name="header">
        <h2 class="h4 m-0">Vales de la Delegacion</h2>
    </x-slot>

    @if(session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger">No se pudo completar la operacion solicitada sobre vales.</div>
    @endif

    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl"><div class="card"><div class="card-body"><div class="text-muted">Total</div><div class="h3 mb-0">{{ $summary['total'] }}</div></div></div></div>
        <div class="col-sm-6 col-xl"><div class="card"><div class="card-body"><div class="text-muted">En oficina</div><div class="h3 mb-0">{{ $summary['oficina'] }}</div></div></div></div>
        <div class="col-sm-6 col-xl"><div class="card"><div class="card-body"><div class="text-muted">En usuario</div><div class="h3 mb-0">{{ $summary['usuario'] }}</div></div></div></div>
        <div class="col-sm-6 col-xl"><div class="card"><div class="card-body"><div class="text-muted">Cerrados</div><div class="h3 mb-0">{{ $summary['cerrado'] }}</div></div></div></div>
        <div class="col-sm-6 col-xl"><div class="card"><div class="card-body"><div class="text-muted">Incidencias</div><div class="h3 mb-0">{{ $summary['incidencias'] }}</div></div></div></div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header">Blocs disponibles en la delegacion</div>
        <div class="card-body table-responsive">
            <table class="table table-sm align-middle">
                <thead>
                    <tr>
                        <th>Rango</th>
                        <th>Estatus</th>
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
                            <td>{{ $bloc->usuario?->name ?? 'Delegacion' }}</td>
                            <td>{{ $bloc->observaciones ?? 'Sin notas' }}</td>
                            <td class="text-end">
                                <div class="d-flex flex-column gap-2 align-items-end">
                                    <form method="POST" action="{{ route('delegacion.inventario.vales.assign', $bloc) }}" class="d-inline-flex gap-2">
                                        @csrf
                                        <select name="usuario_uuid" class="form-select form-select-sm" required>
                                            @foreach($capturistas as $capturista)
                                                <option value="{{ $capturista->uuid }}">{{ $capturista->name }}</option>
                                            @endforeach
                                        </select>
                                        <button class="btn btn-sm btn-outline-primary" type="submit">Asignar</button>
                                    </form>
                                    <form method="POST" action="{{ route('delegacion.inventario.vales.status', $bloc) }}" class="d-inline-flex gap-2">
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
                        <tr><td colspan="5" class="text-muted">Sin vales registrados en esta delegacion.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($valeBlocs->hasPages())
            <div class="card-footer">{{ $valeBlocs->links() }}</div>
        @endif
    </div>
</x-app-layout>
