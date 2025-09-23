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
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-dark table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Estado</th>
                            <th>Ciudad</th>
                            <th>Direccion</th>
                            <th class="text-center">Activa</th>
                            <th class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($sites as $site)
                            <tr @class(['table-secondary'=> $site->trashed()])>
                                <td>{{ $site->name }}</td>
                                <td>{{ $site->state }}</td>
                                <td>{{ $site->city }}</td>
                                <td>{{ $site->address }}</td>
                                <td class="text-center">
                                    @if($site->active)
                                        <span class="badge bg-success">Activa</span>
                                    @else
                                        <span class="badge bg-warning text-dark">Inactiva</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    <div class="btn-group" role="group">
                                        @can('update', $site)
                                            <button class="btn btn-sm btn-outline-light" data-bs-toggle="collapse" data-bs-target="#edit-site-{{ $site->id }}">Editar</button>
                                        @endcan
                                        @can('delete', $site)
                                            <form action="{{ route('vol.sites.destroy', $site) }}" method="POST" onsubmit="return confirm('Eliminar la sede {{ $site->name }}?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-danger">Eliminar</button>
                                            </form>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                            @can('update', $site)
                                <tr class="collapse bg-body-secondary" id="edit-site-{{ $site->id }}">
                                    <td colspan="6">
                                        <form action="{{ route('vol.sites.update', $site) }}" method="POST" class="row g-3">
                                            @csrf
                                            @method('PUT')
                                            <div class="col-md-4">
                                                <label class="form-label">Nombre</label>
                                                <input type="text" name="name" value="{{ old('name', $site->name) }}" class="form-control" required>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label">Estado</label>
                                                <input type="text" name="state" value="{{ old('state', $site->state) }}" class="form-control" required>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label">Ciudad</label>
                                                <input type="text" name="city" value="{{ old('city', $site->city) }}" class="form-control" required>
                                            </div>
                                            <div class="col-12">
                                                <label class="form-label">Direccion</label>
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
                                    </td>
                                </tr>
                            @endcan
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-4">No hay sedes registradas.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if(method_exists($sites, 'links'))
            <div class="card-footer">{{ $sites->links() }}</div>
        @endif
    </div>
</x-app-layout>