<x-app-layout>
    <x-slot name="header">
        <h2 class="h4 m-0">Panel de Delegacion</h2>
    </x-slot>

    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-2"><div class="card"><div class="card-body"><div class="text-muted">Tarjetas oficina</div><div class="h3 mb-0">{{ $stats['tarjetas_oficina'] }}</div></div></div></div>
        <div class="col-sm-6 col-xl-2"><div class="card"><div class="card-body"><div class="text-muted">Tarjetas usuario</div><div class="h3 mb-0">{{ $stats['tarjetas_usuario'] }}</div></div></div></div>
        <div class="col-sm-6 col-xl-2"><div class="card"><div class="card-body"><div class="text-muted">Consumidas</div><div class="h3 mb-0">{{ $stats['tarjetas_consumidas'] }}</div></div></div></div>
        <div class="col-sm-6 col-xl-2"><div class="card"><div class="card-body"><div class="text-muted">Pendientes</div><div class="h3 mb-0">{{ $stats['tarjetas_pendientes'] }}</div></div></div></div>
        <div class="col-sm-6 col-xl-2"><div class="card"><div class="card-body"><div class="text-muted">Vales oficina</div><div class="h3 mb-0">{{ $stats['vales_oficina'] }}</div></div></div></div>
        <div class="col-sm-6 col-xl-2"><div class="card"><div class="card-body"><div class="text-muted">Vales usuario</div><div class="h3 mb-0">{{ $stats['vales_usuario'] }}</div></div></div></div>
    </div>

    <div class="row g-3">
        <div class="col-12 col-lg-4">
            <div class="card shadow-sm h-100">
                <div class="card-header">Operaciones rapidas</div>
                <div class="card-body d-grid gap-2">
                    <a href="{{ route('delegacion.inventario.tarjetas.index') }}" class="btn btn-primary">Administrar tarjetas</a>
                    <a href="{{ route('delegacion.inventario.vales.index') }}" class="btn btn-outline-primary">Administrar vales</a>
                    <a href="{{ route('beneficiarios.create') }}" class="btn btn-outline-secondary">Capturar beneficiario</a>
                    <a href="{{ route('inscripciones.index') }}" class="btn btn-outline-secondary">Registrar inscripcion</a>
                </div>
            </div>
        </div>
        <div class="col-12 col-lg-8">
            <div class="card shadow-sm h-100">
                <div class="card-header">Ultimas tarjetas en la delegacion</div>
                <div class="card-body table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Folio</th>
                                <th>Estatus</th>
                                <th>Responsable</th>
                                <th>Actualizacion</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($recentCards as $tarjeta)
                                <tr>
                                    <td class="font-monospace">{{ $tarjeta->folio }}</td>
                                    <td>{{ $tarjeta->estatus }}</td>
                                    <td>{{ $tarjeta->usuario?->name ?? 'Sin asignar' }}</td>
                                    <td>{{ optional($tarjeta->updated_at)->format('Y-m-d H:i') }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-muted">Aun no hay tarjetas registradas en esta oficina.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
