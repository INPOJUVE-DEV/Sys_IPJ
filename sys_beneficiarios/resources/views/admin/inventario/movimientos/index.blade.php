<x-app-layout>
    <x-slot name="header">
        <h2 class="h4 m-0">Movimientos de Inventario</h2>
    </x-slot>

    <div class="card shadow-sm">
        <div class="card-header">Ultimos movimientos</div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm align-middle">
                    <thead>
                        <tr>
                            <th>Tipo recurso</th>
                            <th>Folio</th>
                            <th>Movimiento</th>
                            <th>Actor</th>
                            <th>Fecha</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($movimientos as $movimiento)
                            <tr>
                                <td>
                                    <span class="badge {{
                                        match ($movimiento['tipo_recurso']) {
                                            'tarjeta' => 'bg-primary',
                                            default => 'bg-success',
                                        }
                                    }}">
                                        {{ $movimiento['tipo_recurso'] }}
                                    </span>
                                </td>
                                <td class="font-monospace">{{ $movimiento['folio'] ?? 'N/D' }}</td>
                                <td>{{ $movimiento['tipo'] }}</td>
                                <td>{{ $movimiento['actor'] ?? 'Sistema' }}</td>
                                <td>{{ optional($movimiento['created_at'])->format('Y-m-d H:i') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-muted">Sin movimientos recientes</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
