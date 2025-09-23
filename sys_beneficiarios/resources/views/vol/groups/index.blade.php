<x-app-layout>
    <x-slot name="header">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
            <h1 class="h4 m-0">Grupos "Jovenes al Volante"</h1>
            @can('create', App\Models\VolGroup::class)
                <button class="btn btn-primary" type="button" data-bs-toggle="collapse" data-bs-target="#inlineCreateGroup" aria-expanded="false" aria-controls="inlineCreateGroup">
                    Crear grupo
                </button>
            @endcan
        </div>
    </x-slot>

    @if(session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @can('create', App\Models\VolGroup::class)
        <div id="inlineCreateGroup" class="collapse mb-4 {{ $errors->any() ? 'show' : '' }}">
            <div class="card">
                <div class="card-body">
                    <form action="{{ route('vol.groups.store') }}" method="POST" class="row g-3">
                        @csrf
                        <div class="col-md-4">
                            <label class="form-label">Sede</label>
                            <select name="site_id" class="form-select" required>
                                <option value="">Seleccione</option>
                                @foreach($sites as $id => $name)
                                    <option value="{{ $id }}" @selected(old('site_id') == $id)>{{ $name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Nombre del grupo</label>
                            <input type="text" name="name" class="form-control" value="{{ old('name') }}" required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Tipo</label>
                            <select name="type" class="form-select" required>
                                @foreach(['semanal' => 'Semanal', 'sabatino' => 'Sabatino'] as $value => $label)
                                    <option value="{{ $value }}" @selected(old('type') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Plantilla</label>
                            <select name="schedule_template" class="form-select" required>
                                @foreach(['lmv' => 'Lunes a Miercoles', 'mj' => 'Jueves y Viernes', 'sab' => 'Sabado'] as $value => $label)
                                    <option value="{{ $value }}" @selected(old('schedule_template') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Fecha de inicio</label>
                            <input type="date" name="start_date" class="form-control" value="{{ old('start_date', now()->format('Y-m-d')) }}" required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Capacidad</label>
                            <input type="number" name="capacity" min="0" class="form-control" value="{{ old('capacity', 12) }}" required>
                        </div>
                        <div class="col-12">
                            <small class="text-muted">El programa se asigna automaticamente a Jovenes al Volante y la fecha de cierre sera el ultimo dia del mes.</small>
                        </div>
                        <div class="col-12 text-end">
                            <button type="submit" class="btn btn-success">Guardar grupo</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endcan

    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label">Sede</label>
                    <select name="site_id" class="form-select">
                        <option value="">Todas</option>
                        @foreach($sites as $id => $name)
                            <option value="{{ $id }}" @selected(($filters['site_id'] ?? '') == $id)>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Estado</label>
                    <select name="state" class="form-select">
                        <option value="">Todos</option>
                        @foreach(['borrador' => 'Borrador', 'publicado' => 'Publicado', 'cerrado' => 'Cerrado'] as $value => $label)
                            <option value="{{ $value }}" @selected(($filters['state'] ?? '') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Tipo</label>
                    <select name="type" class="form-select">
                        <option value="">Todos</option>
                        @foreach(['semanal' => 'Semanal', 'sabatino' => 'Sabatino'] as $value => $label)
                            <option value="{{ $value }}" @selected(($filters['type'] ?? '') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Busqueda</label>
                    <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" class="form-control" placeholder="Nombre o codigo">
                </div>
                <div class="col-12 text-end">
                    <button class="btn btn-outline-light">Filtrar</button>
                </div>
            </form>
        </div>
    </div>

    <div class="row g-3">
        @forelse($groups as $group)
            @php
                $enrolled = $group->enrollments_count ?? (($group->enrollments ?? collect())->where('status', 'inscrito')->count());
                $capacity = $group->capacity ?? 0;
                $available = max(0, $capacity - $enrolled);
            @endphp
            <div class="col-12 col-md-6 col-xl-4">
                <div class="card h-100">
                    <div class="card-body d-flex flex-column">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div>
                                <h2 class="h5 mb-1">{{ $group->name }}</h2>
                                <div class="text-muted small">Codigo: {{ $group->code }}</div>
                                <div class="text-muted small">Sede: {{ $group->site->name ?? 'N/D' }}</div>
                            </div>
                            <span class="badge bg-{{ $group->state === 'publicado' ? 'success' : ($group->state === 'cerrado' ? 'danger' : 'secondary') }} text-uppercase">{{ $group->state }}</span>
                        </div>

                        <dl class="row small mb-3">
                            <dt class="col-6">Tipo</dt><dd class="col-6">{{ $group->type }}</dd>
                            <dt class="col-6">Horario</dt><dd class="col-6">{{ strtoupper($group->schedule_template) }}</dd>
                            <dt class="col-6">Inicio</dt><dd class="col-6">{{ optional($group->start_date)->format('Y-m-d') }}</dd>
                            <dt class="col-6">Fin</dt><dd class="col-6">{{ optional($group->end_date)->format('Y-m-d') ?? 'N/D' }}</dd>
                        </dl>

                        <div class="mb-3">
                            <div class="d-flex justify-content-between"><span>Capacidad</span><strong>{{ $capacity }}</strong></div>
                            <div class="d-flex justify-content-between"><span>Inscritos</span><strong>{{ $enrolled }}</strong></div>
                            <div class="d-flex justify-content-between"><span>Disponibles</span><strong>{{ $available }}</strong></div>
                        </div>

                        <div class="mt-auto d-flex flex-wrap gap-2">
                            @can('view', $group)
                                <a href="{{ route('vol.groups.show', $group) }}" class="btn btn-sm btn-outline-light">Detalle</a>
                            @endcan

                            @can('update', $group)
                                <a href="{{ route('vol.groups.edit', $group) }}" class="btn btn-sm btn-outline-primary">Editar</a>
                                <form action="{{ route('vol.groups.publish', $group) }}" method="POST" class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-success" @disabled($group->state === 'publicado')>Publicar</button>
                                </form>
                                <form action="{{ route('vol.groups.close', $group) }}" method="POST" class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-warning" @disabled($group->state === 'cerrado')>Cerrar</button>
                                </form>
                            @endcan

                            @can('delete', $group)
                                <form action="{{ route('vol.groups.destroy', $group) }}" method="POST" class="d-inline" onsubmit="return confirm('Eliminar el grupo {{ $group->name }}?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger">Eliminar</button>
                                </form>
                            @endcan
                        </div>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-12">
                <div class="alert alert-info">No se encontraron grupos con los filtros seleccionados.</div>
            </div>
        @endforelse
    </div>

    @if(method_exists($groups, 'links'))
        <div class="mt-4">{{ $groups->links() }}</div>
    @endif
</x-app-layout>
