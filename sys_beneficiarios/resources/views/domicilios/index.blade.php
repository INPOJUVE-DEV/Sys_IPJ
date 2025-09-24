<x-app-layout>
    <x-slot name="header">
        <div class="d-flex align-items-center justify-content-between">
            <h2 class="h4 m-0">Domicilios</h2>
            <a href="{{ route('domicilios.create') }}" class="btn btn-primary">Nuevo</a>
        </div>
    </x-slot>

    @if(session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <form class="row g-2 mb-3" method="GET">
        <div class="col-auto">
            <input type="text" name="q" value="{{ $q }}" class="form-control" placeholder="Buscar calle, colonia, municipio, CP o seccional">
        </div>
        <div class="col-auto">
            <button class="btn btn-outline-secondary" type="submit">Buscar</button>
        </div>
    </form>

    <div class="card shadow-sm">
        <div class="card-body">
            <div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-3">
                @forelse($domicilios as $d)
                    <div class="col">
                        <div class="card bg-dark border border-white text-white h-100 shadow-sm">
                            <div class="card-body d-flex flex-column gap-3">
                                <div>
                                    <h3 class="h6 text-white mb-1">{{ optional($d->beneficiario)->nombre }} {{ optional($d->beneficiario)->apellido_paterno }}</h3>
                                    <div class="small text-white-50"><i class="bi bi-geo-alt me-1"></i>{{ $d->municipio }}</div>
                                </div>
                                <div class="small text-white-50 d-flex flex-column gap-1">
                                    <div><i class="bi bi-signpost-2 me-1"></i>{{ $d->calle }} {{ $d->numero_ext }}{{ $d->numero_int? ' Int '.$d->numero_int:'' }}</div>
                                    <div><i class="bi bi-buildings me-1"></i>{{ $d->colonia }}</div>
                                    <div><i class="bi bi-mailbox me-1"></i>CP {{ $d->codigo_postal }}</div>
                                    <div><i class="bi bi-diagram-3 me-1"></i>Seccional {{ $d->seccional }}</div>
                                </div>
                                <div class="mt-auto d-flex flex-column gap-2">
                                    <a href="{{ route('domicilios.edit', $d) }}" class="btn btn-outline-light btn-sm w-100">
                                        <i class="bi bi-pencil-square me-1"></i>Editar
                                    </a>
                                    <form action="{{ route('domicilios.destroy', $d) }}" method="POST" class="m-0" onsubmit="return confirm('¿Eliminar domicilio?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-outline-danger btn-sm w-100">
                                            <i class="bi bi-trash me-1"></i>Eliminar
                                        </button>
                                    </form>
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
        @if($domicilios->hasPages())
            <div class="card-footer">{{ $domicilios->links() }}</div>
        @endif
    </div>
</x-app-layout>

