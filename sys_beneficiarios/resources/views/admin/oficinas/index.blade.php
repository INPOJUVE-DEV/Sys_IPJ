<x-app-layout>
    <x-slot name="header">
        <h2 class="h4 m-0">Oficinas y Delegaciones</h2>
    </x-slot>

    @if(session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div class="row g-3 mb-4">
        @foreach($offices as $office)
            @php($cardStats = collect($cardsByOffice->get($office->id, []))->pluck('total', 'estatus'))
            <div class="col-12 col-lg-6">
                <div class="card shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div>
                                <h3 class="h5 mb-1">{{ $office->nombre }}</h3>
                                <div class="text-muted text-uppercase small">{{ $office->tipo }}</div>
                            </div>
                            <span class="badge {{ $office->activo ? 'bg-success' : 'bg-secondary' }}">{{ $office->activo ? 'Activa' : 'Inactiva' }}</span>
                        </div>

                        <div class="row g-2 small">
                            <div class="col-6"><strong>Usuarios:</strong> {{ $office->users_count }}</div>
                            <div class="col-6"><strong>Municipios:</strong> {{ $office->municipios_count }}</div>
                            <div class="col-6"><strong>Tarjetas:</strong> {{ $office->tarjetas_count }}</div>
                            <div class="col-6"><strong>Disponibles:</strong> {{ $cardStats[\App\Models\Tarjeta::STATUS_DISPONIBLE] ?? 0 }}</div>
                            <div class="col-6"><strong>Asignadas oficina:</strong> {{ $cardStats[\App\Models\Tarjeta::STATUS_ASIGNADA_OFICINA] ?? 0 }}</div>
                            <div class="col-6"><strong>Asignadas usuario:</strong> {{ $cardStats[\App\Models\Tarjeta::STATUS_ASIGNADA_USUARIO] ?? 0 }}</div>
                            <div class="col-6"><strong>Consumidas:</strong> {{ $cardStats[\App\Models\Tarjeta::STATUS_CONSUMIDA] ?? 0 }}</div>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="card shadow-sm">
        <div class="card-header">Municipios por oficina</div>
        <div class="card-body table-responsive">
            <table class="table table-sm align-middle">
                <thead>
                    <tr>
                        <th>Municipio</th>
                        <th>Oficina asignada</th>
                        <th class="text-end">Acción</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($municipios as $municipio)
                        <tr>
                            <td>{{ $municipio->nombre }}</td>
                            <td>{{ $municipio->oficina?->nombre ?? 'Sin asignar' }}</td>
                            <td class="text-end">
                                <form method="POST" action="{{ route('admin.oficinas.municipios.assign', $municipio) }}" class="d-inline-flex gap-2">
                                    @csrf
                                    <select name="oficina_id" class="form-select form-select-sm">
                                        <option value="">Sin asignar</option>
                                        @foreach($offices as $office)
                                            <option value="{{ $office->id }}" @selected((string) $municipio->oficina_id === (string) $office->id)>
                                                {{ $office->nombre }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <button class="btn btn-sm btn-primary" type="submit">Guardar</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
