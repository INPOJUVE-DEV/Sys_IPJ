<x-app-layout>
    <x-slot name="header"><h2 class="h4 m-0">Mis registros</h2></x-slot>

    @if(session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div class="card shadow-sm">
        <div class="card-body">
            <div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-3">
                @forelse($items as $b)
                    <div class="col">
                        <div class="card bg-dark border border-white text-white h-100 shadow-sm">
                            <div class="card-body d-flex flex-column gap-3">
                                <div>
                                    <div class="d-flex justify-content-between align-items-start gap-2">
                                        <div>
                                            <span class="text-white-50 small text-uppercase">Folio</span>
                                            <div class="h6 text-white mb-0">{{ $b->folio_tarjeta }}</div>
                                        </div>
                                        <span class="badge bg-secondary text-white">Registrado</span>
                                    </div>
                                    <div class="fw-semibold">{{ $b->nombre }} {{ $b->apellido_paterno }} {{ $b->apellido_materno }}</div>
                                </div>
                                <div class="small text-white-50 d-flex flex-column gap-1">
                                    <div><i class="bi bi-geo-alt me-1"></i>{{ optional($b->municipio)->nombre ?? 'Sin municipio' }}</div>
                                    <div><i class="bi bi-diagram-3 me-1"></i>Seccional {{ $b->seccional ?? 'N/D' }}</div>
                                </div>
                                <div class="mt-auto d-flex flex-column gap-2">
                                    <a class="btn btn-outline-light btn-sm w-100" href="{{ route('mis-registros.show', $b) }}">
                                        <i class="bi bi-eye me-1"></i>Ver
                                    </a>
                                    <a class="btn btn-outline-secondary btn-sm w-100" href="{{ route('mis-registros.edit', $b) }}">
                                        <i class="bi bi-pencil-square me-1"></i>Editar
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="col-12">
                        <div class="text-center text-muted py-4">Sin registros</div>
                    </div>
                @endforelse
            </div>
        </div>
        @if($items->hasPages())
            <div class="card-footer">{{ $items->links() }}</div>
        @endif
    </div>
</x-app-layout>

