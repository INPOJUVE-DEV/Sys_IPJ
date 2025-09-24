<x-app-layout>
    <x-slot name="header">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
            <h1 class="h4 m-0">Sedes de Voluntariado</h1>
            @can('create', App\Models\VolSite::class)
                <button class="btn btn-primary" data-bs-toggle="collapse" data-bs-target="#createSiteForm" aria-expanded="false">
                    Nueva sede
                </button>
            @endcan
        </div>
    </x-slot>

    @can('create', App\Models\VolSite::class)
        <div id="createSiteForm" class="collapse show mb-4">
            <div class="card">
                <div class="card-body">
                    <h2 class="h5 mb-3">Registrar sede</h2>
                    <form action="{{ route('vol.sites.store') }}" method="POST" class="row g-3">
                        @csrf
                        <div class="col-md-4">
                            <label class="form-label">Nombre</label>
                            <input type="text" name="name" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Estado</label>
                            <input type="text" name="state" class="form-control" value="San Luis Potosi" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Ciudad</label>
                            <input type="text" name="city" class="form-control" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Direccion</label>
                            <input type="text" name="address" class="form-control" required>
                        </div>
                        <div class="col-12">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" role="switch" name="active" value="1" checked>
                                <label class="form-check-label">Sede activa</label>
                            </div>
                        </div>
                        <div class="col-12 text-end">
                            <button type="submit" class="btn btn-success">Guardar</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endcan

    <div class="card">
        <div class="card-body">
            <div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-3">
                @forelse($sites as $site)
                    <div class="col">
                        <div @class(['card bg-dark border border-white text-white h-100 shadow-sm', 'border-danger border-2' => $site->trashed()])>
                            <div class="card-body d-flex flex-column gap-3">
                                <div>
                                    <div class="d-flex justify-content-between align-items-start gap-2">
                                        <h3 class="h5 text-white mb-1">{{ $site->name }}</h3>
                                        <div class="d-flex flex-column align-items-end gap-1">
                                            @if($site->trashed())
                                                <span class="badge bg-danger">Eliminada</span>
                                            @elseif($site->active)
                                                <span class="badge bg-success">Activa</span>
                                            @else
                                                <span class="badge bg-warning text-dark">Inactiva</span>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="small text-white-50">{{ $site->city }}, {{ $site->state }}</div>
                                </div>
                                <div class="small text-white-50">
                                    <i class="bi bi-geo-alt me-1"></i>{{ $site->address }}
                                </div>
                                <div class="mt-auto d-flex flex-column gap-2">
                                    @can('update', $site)
                                        <button class="btn btn-outline-light btn-sm w-100" data-bs-toggle="collapse" data-bs-target="#edit-site-{{ $site->id }}">
                                            <i class="bi bi-pencil-square me-1"></i>Editar
                                        </button>
                                    @endcan
                                    @can('delete', $site)
                                        <form action="{{ route('vol.sites.destroy', $site) }}" method="POST" class="m-0" onsubmit="return confirm('Eliminar la sede {{ $site->name }}?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-outline-danger btn-sm w-100">
                                                <i class="bi bi-trash me-1"></i>Eliminar
                                            </button>
                                        </form>
                                    @endcan
                                </div>
                                @can('update', $site)
                                    <div class="collapse mt-3" id="edit-site-{{ $site->id }}">
                                        <div class="bg-body text-body rounded-3 p-3">
                                            <form action="{{ route('vol.sites.update', $site) }}" method="POST" class="row g-3">
                                                @csrf
                                                @method('PUT')
                                                <div class="col-md-6">
                                                    <label class="form-label">Nombre</label>
                                                    <input type="text" name="name" value="{{ old('name', $site->name) }}" class="form-control" required>
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label">Estado</label>
                                                    <input type="text" name="state" value="{{ old('state', $site->state) }}" class="form-control" required>
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label">Ciudad</label>
                                                    <input type="text" name="city" value="{{ old('city', $site->city) }}" class="form-control" required>
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label">Dirección</label>
                                                    <input type="text" name="address" value="{{ old('address', $site->address) }}" class="form-control" required>
                                                </div>
                                                <div class="col-12">
                                                    <div class="form-check form-switch">
                                                        <input class="form-check-input" type="checkbox" role="switch" name="active" value="1" @checked($site->active)>
                                                        <label class="form-check-label">Sede activa</label>
                                                    </div>
                                                </div>
                                                <div class="col-12 text-end">
                                                    <button type="submit" class="btn btn-primary">Actualizar</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                @endcan
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="col-12">
                        <div class="text-center text-muted py-4">No hay sedes registradas.</div>
                    </div>
                @endforelse
            </div>
        </div>
        @if(method_exists($sites, 'links'))
            <div class="card-footer">{{ $sites->links() }}</div>
        @endif
    </div>
</x-app-layout>